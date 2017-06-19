/**
 * @module package/quiqqer/order/bin/backend/controls/panels/Orders
 *
 * @requnre qui/QUI
 * @requnre qui/controls/desktop/Panel
 * @requnre package/quiqqer/order/bin/backend/Orders
 * @requnre Locale
 * @requnre Mustache
 * @requnre text!package/quiqqer/order/bin/backend/controls/panels/Order.Data.html
 */
define('package/quiqqer/order/bin/backend/controls/panels/Order', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'package/quiqqer/order/bin/backend/Orders',
    'Locale',
    'Mustache',

    'text!package/quiqqer/order/bin/backend/controls/panels/Order.Data.html'

], function (QUI, QUIPanel, Orders, QUILocale, Mustache, templateData) {
    "use strict";

    var lg = 'quiqqer/order';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/order/bin/backend/controls/panels/Order',

        Binds: [
            'refresh',
            'openInfo',
            'openPayments',
            'openArticles',
            '$onCreate',
            '$onResize',
            '$onInject'
        ],

        options: {
            orderId : false,
            data    : {},
            articles: []
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                icon : 'fa fa-shopping-cart',
                title: QUILocale.get(lg, 'order.panel.title', {
                    orderId: this.getAttribute('orderId')
                })
            });

            this.addEvents({
                onCreate: this.$onCreate,
                onResize: this.$onResize,
                onInject: this.$onInject
            });
        },

        /**
         * Refresh the grid
         */
        refresh: function () {

        },

        /**
         * event : on create
         */
        $onCreate: function () {
            this.addCategory({
                icon  : 'fa fa-info',
                name  : 'info',
                title : QUILocale.get(lg, 'panel.order.category.data'),
                text  : QUILocale.get(lg, 'panel.order.category.data'),
                events: {
                    onClick: this.openInfo
                }
            });

            this.addCategory({
                icon  : 'fa fa-credit-card',
                name  : 'payment',
                title : QUILocale.get(lg, 'panel.order.category.payment'),
                text  : QUILocale.get(lg, 'panel.order.category.payment'),
                events: {
                    onClick: this.openPayments
                }
            });

            this.addCategory({
                icon  : 'fa fa-shopping-basket',
                name  : 'articles',
                title : QUILocale.get(lg, 'panel.order.category.articles'),
                text  : QUILocale.get(lg, 'panel.order.category.articles'),
                events: {
                    onClick: this.openArticles
                }
            });
        },

        /**
         * event : on resize
         */
        $onResize: function () {

        },

        /**
         * event: on inject
         */
        $onInject: function () {
            this.openInfo();
        },

        //region categories

        /**
         * Open the information category
         */
        openInfo: function () {
            var self = this;

            this.Loader.show();
            this.getCategory('info').setActive();

            return this.$closeCategory().then(function (Container) {
                Container.set({
                    html: Mustache.render(templateData, {
                        textOrderRecipient: QUILocale.get(lg, 'cutomerData'),
                        textCustomer      : QUILocale.get(lg, 'customer'),
                        textCompany       : QUILocale.get(lg, 'company'),
                        textStreet        : QUILocale.get(lg, 'street'),
                        textZip           : QUILocale.get(lg, 'zip'),
                        textCity          : QUILocale.get(lg, 'city'),
                        textOrderData     : QUILocale.get(lg, 'panel.order.data.title'),
                        textOrderDate     : QUILocale.get(lg, 'panel.order.data.date'),
                        textOrderedBy     : QUILocale.get(lg, 'panel.order.data.orderedBy')
                    })
                });


            }).then(function () {
                return self.$openCategory();
            }).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * Open payment informations
         */
        openPayments: function () {
            var self = this;

            this.Loader.show();
            this.getCategory('payment').setActive();

            return this.$closeCategory().then(function (Container) {

            }).then(function () {
                return self.$openCategory();
            }).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * Open articles
         */
        openArticles: function () {
            var self = this;

            this.Loader.show();
            this.getCategory('articles').setActive();

            return this.$closeCategory().then(function (Container) {

            }).then(function () {
                return self.$openCategory();
            }).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * Open the current category
         *
         * @returns {Promise}
         */
        $openCategory: function () {
            var self = this;

            return new Promise(function (resolve) {
                var Container = self.getContent().getElement('.container');

                if (!Container) {
                    resolve();
                    return;
                }

                moofx(Container).animate({
                    opacity: 1,
                    top    : 0
                }, {
                    duration: 200,
                    callback: resolve
                });
            });
        },

        /**
         * Close the current category
         *
         * @returns {Promise}
         */
        $closeCategory: function () {
            this.getContent().setStyle('padding', 0);

            return new Promise(function (resolve) {
                var Container = this.getContent().getElement('.container');

                if (!Container) {
                    Container = new Element('div', {
                        'class': 'container',
                        styles : {
                            height  : '100%',
                            opacity : 0,
                            position: 'relative',
                            top     : -50
                        }
                    }).inject(this.getContent());
                }

                moofx(Container).animate({
                    opacity: 0,
                    top    : -50
                }, {
                    duration: 200,
                    callback: function () {
                        Container.set('html', '');
                        Container.setStyle('padding', 20);

                        resolve(Container);
                    }.bind(this)
                });
            }.bind(this));
        }

        //endregion categories
    });
});