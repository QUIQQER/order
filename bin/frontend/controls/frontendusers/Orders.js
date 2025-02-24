/**
 * @module package/quiqqer/order/bin/frontend/controls/frontendusers/Orders
 * @author www.pcsg.de (Henning Leutz)
 */

require.config({
    paths: {
        'Navigo'       : URL_OPT_DIR + 'bin/quiqqer-asset/navigo/navigo/lib/navigo.min',
        'HistoryEvents': URL_OPT_DIR + 'bin/quiqqer-asset/history-events/history-events/dist/history-events.min'
    }
});

define('package/quiqqer/order/bin/frontend/controls/frontendusers/Orders', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'Ajax',
    'Locale',
    'HistoryEvents'

], function (QUI, QUIControl, QUILoader, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'quiqqer/order';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/frontendusers/Orders',

        Binds: [
            '$addArticleToBasket',
            '$onChangeState',
            '$onInject',
            '$onImport'
        ],

        options: {
            limit: 10
        },

        initialize: function (options) {
            this.parent(options);

            this.$OrderContainer = null;
            this.$orderOpened    = false;
            this.$location       = null;

            this.$List  = null;
            this.Loader = new QUILoader();

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject
            });

            window.addEventListener('changestate', this.$onChangeState, false);
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this,
                Elm  = this.getElm(),
            SectionContainer = Elm.querySelector('[data-ref="section-container"]');

            this.$List = Elm.getElement('[data-ref="order-list"]');

            this.$OrderContainer = new Element('div', {
                'class': 'quiqqer-order-profile-orders-order-container',
                styles : {
                    left    : -20,
                    opacity : 0,
                    position: 'relative'
                }
            });

            if (SectionContainer) {
                this.$OrderContainer.inject(SectionContainer);
                this.Loader.inject(SectionContainer);
            } else {
                this.$OrderContainer.inject(Elm);
                this.Loader.inject(Elm);
            }

            this.$setEvents();

            // pagination events
            var paginates = Elm.getElements(
                '[data-qui="package/quiqqer/controls/bin/navigating/Pagination"]'
            );

            paginates.addEvent('load', function () {
                var Pagination = QUI.Controls.getById(
                    this.getProperty('data-quiid')
                );

                Pagination.addEvents({
                    onChange: function (Pagination, Sheet, Query) {
                        self.$refreshOrder(Query.sheet, Query.limit);
                    }
                });

                (function () {
                    this.$redraw();
                }).delay(1000, Pagination);
            });

            this.$location = window.location.href;

            if (window.location.hash !== '') {
                this.$onChangeState();
            }
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this,
                Elm  = this.getElm();

            if (Elm.get('html') !== '') {
                return;
            }

            QUIAjax.get('package_quiqqer_order_ajax_frontend_orders_userOrders', function (result) {
                var Ghost = new Element('div', {
                    html: result
                });

                var Orders = Ghost.getElement('.quiqqer-order-profile-orders');

                Orders.replaces(Elm);

                self.$Elm = Orders;
                self.$onImport();
            }, {
                'package': 'quiqqer/order',
                page     : 1,
                limit    : this.getAttribute('limit') || 10
            });
        },

        /**
         * event: change url state
         */
        $onChangeState: function () {
            var self = this,
                hash = window.location.hash;

            if (this.$location === null) {
                return;
            }


            if (hash === '') {
                if (this.$orderOpened) {
                    this.$hideOrderContainer().then(function () {
                        return self.$showList();
                    });
                }

                this.$orderOpened = '';
                return;
            }

            hash = hash.replace('#', '');

            if (this.$orderOpened === hash || !this.$isOrderHash(hash)) {
                return;
            }


            this.Loader.show();

            self.$orderOpened = hash;

            require([
                'package/quiqqer/order/bin/frontend/controls/order/Order'
            ], function (Order) {
                new Order({
                    hash  : self.$orderOpened,
                    events: {
                        onLoad: function () {
                            self.Loader.hide();
                            self.$hideList().then(function () {
                                self.$OrderContainer.scrollIntoView({
                                    behavior: "smooth",
                                    block: "start"
                                });

                                return self.$showOrderContainer();
                            });
                        }
                    }
                }).inject(self.$OrderContainer);

                new Element('button', {
                    html   : QUILocale.get(lg, 'control.orders.backButton'),
                    'class': 'quiqqer-order-control-order-backButton',
                    events : {
                        click: function (event) {
                            event.stop();

                            self.$hideOrderContainer().then(function () {
                                return self.$showList();
                            });

                            window.location.hash = '';
                            self.$orderOpened    = '';
                        }
                    }
                }).inject(self.$OrderContainer);
            });
        },

        /**
         * Refresh the order listing
         *
         * @param {number} page
         * @param {number} limit
         */
        $refreshOrder: function (page, limit) {
            var self = this;

            this.Loader.show();

            if (typeof limit === 'undefined') {
                limit = this.getAttribute('limit');
            }

            QUIAjax.get('package_quiqqer_order_ajax_frontend_orders_userOrders', function (result) {
                var Ghost = new Element('div', {
                    html: result
                });

                self.$List.set(
                    'html',
                    Ghost.getElement('.quiqqer-order-profile-orders-list').get('html')
                );

                QUI.parse(self.$List).then(function () {
                    self.$setEvents();
                    self.Loader.hide();
                });
            }, {
                'package': 'quiqqer/order',
                page     : page,
                limit    : limit
            });
        },

        /**
         * Set click / mouse / touch events
         */
        $setEvents: function () {
            var self       = this;
            var orderLinks = this.getElm().getElements('[data-ref="order-link"]');

            orderLinks.addEvent('click', function (event) {
                var Target = event.target;

                if (Target.nodeName !== 'A') {
                    Target = Target.getParent('a');
                }

                var hash = Target.get('data-hash');

                if (!hash) {
                    return;
                }

                self.Loader.show();

                event.stop();

                window.location.hash = hash;
                self.$onChangeState();
            });
        },

        //region utils

        /**
         * Is the hash an order hash?
         * can be used for the first check
         *
         * @param hash
         * @return {boolean}
         */
        $isOrderHash: function (hash) {
            var count = (hash.match(/-/g) || []).length;
            return count === 4;
        },

        /**
         * hide the order list
         *
         * @return {Promise}
         */
        $hideList: function () {
            var self = this;

            var elements = this.getElm().getElements(
                '[data-qui="package/quiqqer/controls/bin/navigating/Pagination"]'
            );

            elements.push(self.$List);
            elements.push(...this.getElm().querySelectorAll('[data-ref="order-text"]'))

            return new Promise(function (resolve) {
                elements.setStyle('position', 'relative');

                moofx(elements).animate({
                    left   : -20,
                    opacity: 0
                }, {
                    duration: 250,
                    callback: function () {
                        elements.setStyle('display', 'none');
                        resolve();
                    }
                });
            });
        },

        /**
         * show the order list
         *
         * @return {Promise}
         */
        $showList: function () {
            var self = this;

            var elements = this.getElm().getElements(
                '[data-qui="package/quiqqer/controls/bin/navigating/Pagination"]'
            );

            elements.push(self.$List);
            elements.push(...this.getElm().querySelectorAll('[data-ref="order-text"]'))

            elements.setStyle('display', null);

            return new Promise(function (resolve) {
                moofx(elements).animate({
                    left   : 0,
                    opacity: 1
                }, {
                    duration: 250,
                    callback: function () {
                        self.$List.setStyles({
                            left    : null,
                            opacity : null,
                            position: null
                        });

                        resolve();
                    }
                });
            });
        },

        /**
         * shows the order container
         *
         * @return {Promise}
         */
        $showOrderContainer: function () {
            var self = this;

            return new Promise(function (resolve) {
                self.$OrderContainer.setStyles({
                    left    : -20,
                    opacity : 0,
                    position: 'relative'
                });

                moofx(self.$OrderContainer).animate({
                    left   : 0,
                    opacity: 1
                }, {
                    duration: 250,
                    callback: resolve
                });
            });
        },

        /**
         * hide the order container
         *
         * @return {Promise}
         */
        $hideOrderContainer: function () {
            var self = this;

            return new Promise(function (resolve) {
                moofx(self.$OrderContainer).animate({
                    left   : -20,
                    opacity: 0
                }, {
                    duration: 250,
                    callback: function () {
                        self.$OrderContainer.setStyle('display', '');
                        self.$OrderContainer.set('html', '');
                        resolve();
                    }
                });
            });
        }

        //endregion
    });
});
