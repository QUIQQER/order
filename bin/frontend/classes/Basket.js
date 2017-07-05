/**
 * @module package/quiqqer/order/bin/frontend/classes/Basket
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 * @require Locale
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

        initialize: function (options) {
            this.parent(options);

            this.$isLoaded = false;
            this.$articles = [];
            this.$orders   = {};
            this.$orderid  = false;

            this.$isLoaded  = false;
            this.$saveDelay = null;
        },

        /**
         * Load the basket
         */
        load: function () {
            // basket from user
            if (QUIQQER_USER && QUIQQER_USER.id) {
                return this.loadLastOrder().then(function () {
                    this.$isLoaded = true;
                    this.fireEvent('refresh', [this]);
                }.bind(this));
            }

            var articles = [],
                data     = QUI.Storage.get('product-order');

            this.$orderid = String.uniqueID();

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
                this.$orderid = data.currentList;
            }

            if (typeof data.articles !== 'undefined' &&
                typeof data.articles[this.$orderid] !== 'undefined') {
                articles = data.articles[this.$orderid];
            }

            var proms = [];

            for (var i = 0, len = articles.length; i < len; i++) {
                proms.push(
                    this.addArticle(
                        articles[i].id,
                        articles[i].quantity,
                        articles[i].fields
                    )
                );
            }

            return Promise.all(proms).then(function () {
                var self = this;

                if (this.getAttribute('productsNotExists')) {
                    QUI.getMessageHandler().then(function (MH) {
                        MH.addError(
                            QUILocale.get(lg, 'message.article.removed')
                        );
                        self.getAttribute('productsNotExists', false);
                    });
                }

                this.$isLoaded = true;
                this.fireEvent('refresh', [this]);
            }.bind(this));
        },

        /**
         * Return the loading status of the watchlist
         * is the watchlist already loaded?
         *
         * @returns {boolean}
         */
        isLoaded: function () {
            return this.$isLoaded;
        },

        /**
         * Return the quantity of the products in the current list
         *
         * @returns {Number}
         */
        getQuantity: function () {
            var quantity = 0;

            for (var i in this.$articles) {
                if (this.$articles.hasOwnProperty(i)) {
                    quantity = parseInt(quantity) + parseInt(this.$articles[i].getQuantity());
                }
            }

            return quantity;
        },

        /**
         * Load the last order from the user
         *
         * @return {Promise}
         */
        loadLastOrder: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                self.getLastOrder().then(function (order) {
                    return self.$loadOrderData(order);
                }).then(resolve, reject);
            });
        },

        /**
         *
         * @param data
         * @return {Promise}
         */
        $loadOrderData: function (data) {
            var articles = data.articles;

            if (typeof data.articles.articles === 'undefined') {
                return Promise.resolve();
            }

            if (!data.articles.articles.length) {
                return Promise.resolve();
            }


            console.log('######');
            console.log(articles);

            return new Promise(function (resolve) {
                resolve();
            });
        },

        /**
         * Return a specific order
         *
         * @param {Number} orderId
         * @return {Promise}
         */
        getOrder: function (orderId) {
            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_basket_get', resolve, {
                    'package': 'quiqqer/order',
                    orderId  : orderId
                });
            }.bind(this));
        },

        /**
         * Return the last order of the session user
         *
         * @return {Promise}
         */
        getLastOrder: function () {
            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_basket_getLastOrder', resolve, {
                    'package': 'quiqqer/order'
                });
            }.bind(this));
        },

        /**
         * Return the data of all lists
         *
         * @returns {Promise}
         */
        getLists: function () {
            if (Object.getLength(this.$orders)) {
                return Promise.resolve(this.$orders);
            }

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_basket_list', function (result) {
                    this.$orders = result;
                    resolve(result);
                }.bind(this), {
                    'package': 'quiqqer/order'
                });
            }.bind(this));
        },

        /**
         * Add an article to the basket
         *
         * @param {Number|Object} article
         * @param {Number} quantity
         * @param {Object} fields
         */
        addArticle: function (article, quantity, fields) {
            var self      = this;
            var articleId = article;

            quantity = parseInt(quantity) || 1;
            fields   = fields || {};

            if (!quantity) {
                quantity = 1;
            }

            if (typeOf(article) === 'package/quiqqer/watchlist/bin/classes/Product') {
                articleId = parseInt(article.getId());
            }

            return this.existsProduct(articleId).then(function (available) {
                if (!available) {
                    self.setAttribute('productsNotExists', true);
                    return Promise.resolve();
                }

                this.fireEvent('refreshBegin', [this]);
                this.fireEvent('addBegin');

                return new Promise(function (resolve) {
                    require([
                        'package/quiqqer/order/bin/frontend/classes/Article'
                    ], function (ArticleCls) {
                        var Article = articleId;

                        if (typeOf(articleId) === 'string') {
                            articleId = parseInt(articleId);
                        }

                        if (typeOf(articleId) === 'number') {
                            Article = new ArticleCls({
                                id    : articleId,
                                events: {
                                    onChange: self.$onArticleChange
                                }
                            });
                        }

                        Article.setQuantity(quantity).then(function () {
                            return Article.setFieldValues(fields);
                        }).then(function () {
                            self.$articles.push(Article);
                            return self.save();
                        }).then(function () {
                            resolve();
                            self.fireEvent('refresh', [self]);
                            self.fireEvent('add', [self]);
                        });

                    });
                });
            });
        }
    });
});
