/**
 * @module package/quiqqer/order/bin/frontend/classes/Basket
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onLoad [self]
 * @event onRemoveBegin [self]
 * @event onRemove [self]
 *
 * @event onAddBegin [self]
 * @event onAdd [self, Product]
 *
 * @event onClearBegin [self]
 * @event onClear [self]
 *
 * @event onRefreshBegin [self]
 * @event onRefresh [self]
 */
define('package/quiqqer/order/bin/frontend/classes/Basket', [

    'qui/QUI',
    'qui/classes/DOM',
    'package/quiqqer/order/bin/frontend/Orders',
    'Ajax',
    'Locale'

], function (QUI, QUIDOM, Orders, QUIAjax, QUILocale) {
    "use strict";

    var lg          = 'quiqqer/order';
    var STORAGE_KEY = 'quiqqer-basket-products';

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/quiqqer/order/bin/frontend/classes/Basket',

        Binds: [
            '$onProductChange'
        ],

        options: {
            mergeLocalStorage: 1 // 0 = use old basket, 1 = merge both, 2 = use new one
        },

        initialize: function (options) {
            this.parent(options);

            this.$products  = [];
            this.$basketId  = null;
            this.$orderHash = null;

            this.$isLoaded       = false;
            this.$mergeIsRunning = false;
            this.$saveDelay      = null;

            this.$calculations = {};

        },

        /**
         * Load the basket
         */
        load: function () {
            // basket from user
            if (QUIQQER_USER && QUIQQER_USER.id) {
                return this.$checkLocalBasketLoading().then(function () {
                    this.$isLoaded = true;
                    this.fireEvent('refresh', [this]);
                    this.fireEvent('load', [this]);
                }.bind(this)).catch(function (err) {
                    console.error(err);
                });
            }

            var self     = this,
                products = [],
                data     = QUI.Storage.get(STORAGE_KEY);

            this.$basketId = String.uniqueID();

            if (!data) {
                this.$isLoaded = true;
                return Promise.resolve();
            }

            this.fireEvent('refreshBegin', [this]);

            try {
                data = JSON.decode(data);

                if (!data) {
                    data = {};
                }
            } catch (e) {
                data = {};
            }

            if (typeof data.currentList !== 'undefined') {
                this.$basketId = data.currentList;
            }

            if (typeof data.products !== 'undefined' &&
                typeof data.products[this.$basketId] !== 'undefined') {
                products = data.products[this.$basketId];
            }

            var proms = [];

            for (var i = 0, len = products.length; i < len; i++) {
                proms.push(
                    this.addProduct(
                        products[i].id,
                        products[i].quantity,
                        products[i].fields
                    )
                );
            }

            return Promise.all(proms).then(function () {
                if (self.getAttribute('productsNotExists')) {
                    QUI.getMessageHandler().then(function (MH) {
                        MH.addError(
                            QUILocale.get(lg, 'message.products.removed')
                        );

                        self.getAttribute('productsNotExists', false);
                    });
                }

                self.$isLoaded = true;
                self.save().then(function () {
                    self.fireEvent('refresh', [self]);
                    self.fireEvent('load', [self]);
                });
            });
        },

        /**
         * Return the loading status of the basket
         * is the basket already loaded?
         *
         * @returns {boolean}
         */
        isLoaded: function () {
            return this.$isLoaded;
        },

        /**
         * Return the basket id
         *
         * @return {Number}
         */
        getId: function () {
            return this.$basketId;
        },

        /**
         * Return the order hash, if an order is active
         *
         * @return {null|*}
         */
        getHash: function () {
            return this.$orderHash;
        },

        /**
         * Return the quantity of the products in the current list
         *
         * @returns {Number}
         */
        getQuantity: function () {
            var quantity = 0;

            for (var i in this.$products) {
                if (this.$products.hasOwnProperty(i)) {
                    quantity = parseInt(quantity) + parseInt(this.$products[i].getQuantity());
                }
            }

            return quantity;
        },

        /**
         * Load the current basket of the user
         *
         * @return {Promise}
         */
        loadBasket: function () {
            var self = this;

            return self.getBasket().then(function (basket) {
                self.$calculations = basket;

                return self.$loadData(basket);
            });
        },

        /**
         * Data helper for the products
         *
         * @param data
         * @return {Promise}
         */
        $loadData: function (data) {
            if (data === null || typeof data.products === 'undefined') {
                return Promise.reject('Data is null');
            }

            this.$basketId = data.id;
            this.$products = [];

            if (typeof data.products === 'undefined') {
                return Promise.resolve();
            }

            if (!data.products.length) {
                return Promise.resolve();
            }

            var self     = this;
            var products = data.products;

            return new Promise(function (resolve) {
                require(['package/quiqqer/order/bin/frontend/classes/Product'], function (ProductCls) {
                    for (var i = 0, len = products.length; i < len; i++) {
                        self.$products.push(
                            new ProductCls(products[i])
                        );
                    }

                    resolve();
                });
            });
        },

        /**
         * check if the locale storage has to be considered and integrated into the basket
         */
        $checkLocalBasketLoading: function () {
            var self            = this;
            var storageProducts = [];

            try {
                var storageData = JSON.decode(
                    QUI.Storage.get(STORAGE_KEY)
                );

                var currentList = storageData.currentList;

                if (typeof storageData.products !== 'undefined' &&
                    typeof storageData.products[currentList] !== 'undefined') {
                    storageProducts = storageData.products[currentList];
                }
            } catch (e) {
                // nothing
            }

            return this.getBasket().then(function (basket) {
                if (!storageProducts.length) {
                    self.$calculations = basket;

                    return self.$loadData(basket);
                }

                /**
                 * consider local storage
                 */

                self.$basketId = basket.id;
                self.$products = [];

                var addStorageProducts = function () {
                    var proms = [];

                    for (var i = 0, len = storageProducts.length; i < len; i++) {
                        proms.push(
                            self.addProduct(
                                storageProducts[i].id,
                                storageProducts[i].quantity,
                                storageProducts[i].fields
                            )
                        );
                    }

                    return Promise.all(proms);
                };

                // clear the storage, otherwise the next time we have a double basket.
                QUI.Storage.set(STORAGE_KEY, JSON.encode({}));

                // 0 = use old basket
                if (self.getAttribute('mergeLocalStorage') === 0) {
                    return Promise.resolve();
                }

                // load old one
                self.$mergeIsRunning = true;

                // 2 = use new one
                if (self.getAttribute('mergeLocalStorage') === 2) {
                    return addStorageProducts().then(function () {
                        self.$mergeIsRunning = false;
                    });
                }

                // 1 = merge both
                return self.$loadData(basket).then(function () {
                    return addStorageProducts();
                }).then(function () {
                    self.$mergeIsRunning = false;
                });
            });
        },

        /**
         * Return the basket for the session user
         *
         * @return {Promise}
         */
        getBasket: function () {
            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_basket_getBasket', resolve, {
                    'package': 'quiqqer/order'
                });
            });
        },

        /**
         * Return the basket calculations (sum, subSum, prices)
         *
         * @return {{currencyData: {}, isEuVat: number, isNetto: number, nettoSubSum: number, nettoSum: number, subSum: number, sum: number, vatArray: {}, vatText: {}}}
         */
        getCalculations: function () {
            if ("calculations" in this.$calculations) {
                return this.$calculations.calculations;
            }

            return {
                currencyData: {},
                isEuVat     : 0,
                isNetto     : 0,
                nettoSubSum : 0,
                nettoSum    : 0,
                subSum      : 0,
                sum         : 0,
                vatArray    : {},
                vatText     : {}
            };
        },

        /**
         * Add a product to the basket
         *
         * @param {Number|Object} product
         * @param {Number} [quantity]
         * @param {Object} [fields]
         */
        addProduct: function (product, quantity, fields) {
            var self      = this;
            var productId = product;

            quantity = parseInt(quantity) || 1;
            fields   = fields || {};

            if (!quantity) {
                quantity = 1;
            }

            if (typeOf(product) === 'package/quiqqer/order/bin/frontend/classes/Product') {
                productId = parseInt(product.getId());
                fields    = product.getFields();
                quantity  = product.getQuantity();
            }

            return this.existsProduct(productId).then(function (available) {
                if (!available) {
                    self.setAttribute('productsNotExists', true);
                    return Promise.resolve();
                }

                self.fireEvent('refreshBegin', [this]);
                self.fireEvent('addBegin');

                return new Promise(function (resolve) {
                    require(['package/quiqqer/order/bin/frontend/classes/Product'], function (ProductCls) {
                        var Product = product;

                        if (typeOf(productId) === 'string') {
                            productId = parseInt(productId);
                        }

                        if (typeOf(productId) === 'number') {
                            Product = new ProductCls({
                                id    : productId,
                                events: {
                                    onChange: self.$onProductChange
                                }
                            });
                        }

                        // normal basket
                        Product.setQuantity(quantity).then(function () {
                            return Product.setFieldValues(fields);
                        }).then(function () {
                            self.$products.push(Product);
                            return self.save();
                        }).then(function () {
                            resolve();
                            self.fireEvent('refresh', [self]);
                            self.fireEvent('add', [self, Product]);
                        });
                    });
                });
            }).catch(function (err) {
                console.error(err);
            });
        },

        /**
         * Remove product pos
         *
         * @param pos
         * @return {Promise}
         */
        removeProductPos: function (pos) {
            var self  = this,
                index = pos - 1;

            if (typeof this.$products[index] === 'undefined') {
                return Promise.resolve();
            }

            this.fireEvent('refreshBegin', [this]);
            this.fireEvent('removeBegin', [this]);

            this.$products.splice(index, 1);

            return self.save().then(function () {
                self.fireEvent('remove', [self]);
                self.fireEvent('refresh', [self]);
            });
        },

        /**
         * Clears the basket
         *
         * @return {Promise}
         */
        clear: function () {
            var self = this;

            this.fireEvent('clearBegin', [this]);

            return new Promise(function (resolve) {
                QUIAjax.post('package_quiqqer_order_ajax_frontend_basket_clear', function (result) {
                    self.refresh().then(function () {
                        self.fireEvent('clear', [self]);
                        resolve(result);
                    });
                }, {
                    'package': 'quiqqer/order',
                    basketId : self.$basketId
                });
            });
        },

        /**
         * Refresh the basket data
         *
         * @return {Promise}
         */
        refresh: function () {
            var self = this;

            return this.loadBasket().then(function () {
                self.fireEvent('refresh', [self]);
            }).catch(function (err) {
                if (err !== 'Data is null') {
                    console.error(err);
                }
            });
        },

        /**
         * set quantity for
         *
         * @param pos
         * @param quantity
         * @return {*}
         */
        setQuantity: function (pos, quantity) {
            pos = pos - 1;

            if (typeof this.$products[pos] === 'undefined') {
                return Promise.resolve();
            }

            var self    = this,
                Product = this.$products[pos];

            return Product.setQuantity(quantity).then(function () {
                self.$products[pos] = Product;
                return self.save();
            });
        },

        /**
         * Return the basket products
         *
         * @return {Array}
         */
        getProducts: function () {
            return this.$products;
        },

        /**
         * Returns the number of products
         *
         * @return {Number}
         */
        count: function () {
            return this.getProducts().length;
        },

        /**
         * Saves the basket to the temporary order
         *
         * @param {Boolean} [force] - force the save delay, prevent homemade ddos
         * @return {Promise}
         */
        save: function (force) {
            if (!this.isLoaded() && this.$mergeIsRunning === false) {
                return Promise.resolve();
            }

            var self = this;

            force = force || false;

            return new Promise(function (resolve) {
                if (force === false) {
                    // save delay, prevent homemade ddos
                    if (this.$saveDelay) {
                        clearTimeout(this.$saveDelay);
                    }

                    this.$saveDelay = (function () {
                        this.save(true).then(resolve);
                    }).delay(100, this);

                    return;
                }

                if (!this.$basketId) {
                    resolve();
                    return;
                }

                var products = [];

                for (var i = 0, len = this.$products.length; i < len; i++) {
                    products.push(this.$products[i].getAttributes());
                }

                // no locale storage
                if (QUIQQER_USER && QUIQQER_USER.id) {
                    QUIAjax.post('package_quiqqer_order_ajax_frontend_basket_save', function (result) {
                        self.$calculations = result;
                        resolve(result);
                    }, {
                        'package': 'quiqqer/order',
                        basketId : this.$basketId,
                        products : JSON.encode(products)
                    });

                    return;
                }

                // locale storage
                var data = QUI.Storage.get(STORAGE_KEY);

                if (!data) {
                    data = {};
                } else {
                    data = JSON.decode(data);

                    if (!data) {
                        data = {};
                    }
                }

                if (typeof data.products === 'undefined') {
                    data.products = {};
                }

                data.currentList              = this.$basketId;
                data.products[this.$basketId] = products;

                QUI.Storage.set(STORAGE_KEY, JSON.encode(data));

                QUIAjax.post('package_quiqqer_order_ajax_frontend_basket_calc', function (result) {
                    self.$calculations = result;
                    resolve(result);
                }, {
                    'package': 'quiqqer/order',
                    products : JSON.encode(products)
                });
            }.bind(this));
        },

        /**
         * Exists the product, is it available?
         *
         * @param {String|Number} productId
         * @return {Promise}
         */
        existsProduct: function (productId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_basket_existsProduct', resolve, {
                    'package': 'quiqqer/order',
                    productId: productId,
                    onError  : reject
                });
            });
        },

        /**
         * event : on change
         */
        $onProductChange: function () {
            this.fireEvent('refreshBegin', [this]);

            this.save().then(function () {
                this.fireEvent('refresh', [this]);
            }.bind(this));
        },

        /**
         *
         * @param {String} [orderHash]
         * @return {Promise}
         */
        toOrder: function (orderHash) {
            var self = this;

            if (typeof orderHash === 'undefined') {
                orderHash = '';
            }

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_basket_toOrderInProcess', resolve, {
                    'package': 'quiqqer/order',
                    orderHash: orderHash,
                    basketId : self.$basketId,
                    onError  : reject
                });
            });
        }
    });
});
