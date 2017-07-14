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

        Binds: [
            '$onArticleChange'
        ],

        initialize: function (options) {
            this.parent(options);

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
                    console.warn(this.$articles);
                    this.$isLoaded = true;
                    this.fireEvent('refresh', [this]);
                }.bind(this));
            }

            var articles = [],
                data     = QUI.Storage.get('quiqqer-basket-articles');

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

                if (this.getAttribute('articlesNotExists')) {
                    QUI.getMessageHandler().then(function (MH) {
                        MH.addError(
                            QUILocale.get(lg, 'message.article.removed')
                        );
                        self.getAttribute('articlesNotExists', false);
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
         *
         * @return {*|boolean|Number}
         */
        getCurrentOrderId: function () {
            return this.$orderid;
        },

        /**
         * Return the quantity of the articles in the current list
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

            this.$orderid  = data.id;
            this.$articles = [];

            if (typeof data.articles.articles === 'undefined') {
                return Promise.resolve();
            }

            if (!data.articles.articles.length) {
                return Promise.resolve();
            }

            var promiseList = [];

            articles = articles.articles;

            for (var i = 0, len = articles.length; i < len; i++) {
                promiseList.push(
                    this.addArticle(
                        articles[i].id,
                        articles[i].quantity,
                        articles[i].fields
                    )
                );
            }

            if (!promiseList.length) {
                return Promise.resolve();
            }

            return Promise.all(promiseList);
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

            if (typeOf(article) === 'package/quiqqer/order/bin/frontend/classes/Article') {
                articleId = parseInt(article.getId());
                fields    = article.getFields();
                quantity  = article.getQuantity();
            }

            return this.existsProduct(articleId).then(function (available) {
                if (!available) {
                    self.setAttribute('articlesNotExists', true);
                    return Promise.resolve();
                }

                self.fireEvent('refreshBegin', [this]);
                self.fireEvent('addBegin');

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
            }).catch(function (err) {
                console.error(err);
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

                if (!this.$orderid) {
                    resolve();
                    return;
                }

                var articles = [];

                for (var i = 0, len = this.$articles.length; i < len; i++) {
                    articles.push(this.$articles[i].getAttributes());
                }

                // locale storage
                if (QUIQQER_USER && QUIQQER_USER.id) {
                    QUIAjax.post('package_quiqqer_order_ajax_frontend_basket_save', resolve, {
                        'package': 'quiqqer/order',
                        orderId  : this.$orderid,
                        articles : JSON.encode(articles)
                    });

                    return;
                }


                var data = QUI.Storage.get('quiqqer-basket-articles');

                if (!data) {
                    data = {};
                } else {
                    data = JSON.decode(data);

                    if (!data) {
                        data = {};
                    }
                }

                if (typeof data.articles === 'undefined') {
                    data.articles = {};
                }

                data.currentList            = this.$listid;
                data.articles[this.$listid] = articles;

                QUI.Storage.set('quiqqer-basket-articles', JSON.encode(data));

                resolve();

            }.bind(this));
        },

        /**
         * Exists the article? Is it available?
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
        $onArticleChange: function () {
            this.fireEvent('refreshBegin', [this]);

            this.save().then(function () {
                this.fireEvent('refresh', [this]);
            }.bind(this));
        }
    });
});
