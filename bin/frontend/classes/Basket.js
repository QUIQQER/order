/**
 * @module package/quiqqer/order/bin/frontend/classes/Basket
 */
define('package/quiqqer/order/bin/frontend/classes/Basket', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax',
    'Locale'

], function (QUI, QUIDOM, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'quiqqer/order';

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/quiqqer/order/bin/frontend/classes/Basket',

        Binds: [
            '$onProductChange'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$products = [];
            this.$basketId = null;

            this.$isLoaded  = false;
            this.$saveDelay = null;
        },

        /**
         * Load the basket
         */
        load: function () {
            // basket from user
            if (QUIQQER_USER && QUIQQER_USER.id) {
                return this.loadBasket().then(function () {
                    this.$isLoaded = true;
                    this.fireEvent('refresh', [this]);
                }.bind(this)).catch(function (err) {
                    console.error(err);
                });
            }

            var products = [],
                data     = QUI.Storage.get('quiqqer-basket-products');

            this.$basketId = String.uniqueID();

            if (!data) {
                this.$isLoaded = true;
                return Promise.resolve();
            }


            this.fireEvent('refreshBegin', [this]);

            data = JSON.decode(data);

            if (!data) {
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
                var self = this;

                if (this.getAttribute('productsNotExists')) {
                    QUI.getMessageHandler().then(function (MH) {
                        MH.addError(
                            QUILocale.get(lg, 'message.products.removed')
                        );
                        self.getAttribute('productsNotExists', false);
                    });
                }

                this.$isLoaded = true;
                this.fireEvent('refresh', [this]);
            }.bind(this));
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

            return new Promise(function (resolve, reject) {
                self.getBasket().then(function (basket) {
                    return self.$loadData(basket);
                }).then(resolve, reject);
            });
        },

        /**
         *
         * @param data
         * @return {Promise}
         */
        $loadData: function (data) {
            if (data === null || typeof data.products === 'undefined') {
                return Promise.reject();
            }

            this.$basketId = data.id;
            this.$products = [];

            if (typeof data.products === 'undefined') {
                return Promise.resolve();
            }

            if (!data.products.length) {
                return Promise.resolve();
            }

            var products    = data.products;
            var promiseList = [];

            for (var i = 0, len = products.length; i < len; i++) {
                promiseList.push(
                    this.addProduct(
                        products[i].id,
                        products[i].quantity,
                        products[i].fields
                    )
                );
            }

            if (!promiseList.length) {
                return Promise.resolve();
            }

            return Promise.all(promiseList);
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
         * Add a product to the basket
         *
         * @param {Number|Object} product
         * @param {Number} quantity
         * @param {Object} fields
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

                        Product.setQuantity(quantity).then(function () {
                            return Product.setFieldValues(fields);
                        }).then(function () {
                            self.$products.push(Product);
                            return self.save();
                        }).then(function () {
                            resolve();
                            self.fireEvent('refresh', [self]);
                            self.fireEvent('add', [self]);
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
         */
        removeProductPos: function (pos) {
            var self  = this,
                index = pos - 1;

            if (typeof this.$products[index] === 'undefined') {
                return;
            }

            this.fireEvent('refreshBegin', [this]);
            this.$products.splice(index, 1);
            this.save().then(function () {
                self.fireEvent('refresh', [self]);
            });
        },

        /**
         * Saves the basket to the temporary order
         *
         * @param {Boolean} [force] - force the save delay, prevent homemade ddos
         * @return {Promise}
         */
        save: function (force) {
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

                // locale storage
                if (QUIQQER_USER && QUIQQER_USER.id) {
                    QUIAjax.post('package_quiqqer_order_ajax_frontend_basket_save', resolve, {
                        'package': 'quiqqer/order',
                        basketId : this.$basketId,
                        products : JSON.encode(products)
                    });

                    return;
                }


                var data = QUI.Storage.get('quiqqer-basket-products');

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

                data.currentList            = this.$listid;
                data.products[this.$listid] = products;

                QUI.Storage.set('quiqqer-basket-products', JSON.encode(data));

                resolve();

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
        }
    });
});
