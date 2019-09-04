/**
 * Tha big main basket control
 *
 * @module package/quiqqer/order/bin/frontend/controls/basket/Basket
 */
define('package/quiqqer/order/bin/frontend/controls/basket/Basket', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'Ajax',
    'package/quiqqer/order/bin/frontend/Basket'

], function (QUI, QUIControl, QUILoader, QUIAjax, Basket) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/basket/Basket',

        Binds: [
            '$render',
            '$onImport',
            '$onInject'
        ],

        initialize: function (options) {
            var self = this;

            this.parent(options);

            if (!this.getAttribute('basketId')) {
                this.getAttribute('basketId', Basket.getId());
            }

            this.$Loader    = new QUILoader();
            this.$isInOrder = null;

            this.addEvents({
                onInject : this.$onInject,
                onImport : this.$onImport,
                onDestroy: function () {
                    Basket.removeEvent('onRefresh', self.$onBasketRefresh);
                }
            });

            Basket.addEvents({
                onRefresh: this.$onBasketRefresh
            });
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            this.refresh();
        },

        /**
         * event: on import
         */
        $onImport: function () {
            // user need no import
            if (!this.isGuest()) {
                this.$setEvents();
                this.$Loader.inject(this.getElm());
                return;
            }

            this.refresh();
        },

        /**
         * refresh the display
         */
        refresh: function () {
            var self     = this,
                basketId = this.getAttribute('basketId');

            if (basketId === false) {
                basketId = Basket.getId();
            }

            // guest
            if (this.isGuest()) {
                var Loaded = Promise.resolve();

                if (!Basket.isLoaded()) {
                    Loaded = new Promise(function (resolve) {
                        Basket.addEvent('load', resolve);
                    });
                }

                Loaded.then(function () {
                    var products       = [];
                    var basketProducts = Basket.getProducts();

                    for (var i = 0, len = basketProducts.length; i < len; i++) {
                        products.push(basketProducts[i].getAttributes());
                    }

                    QUIAjax.get('package_quiqqer_order_ajax_frontend_basket_controls_basketGuest', self.$render, {
                        'package': 'quiqqer/order',
                        products : JSON.encode(products),
                        options  : JSON.encode({
                            buttons: false
                        })
                    });
                });

                return;
            }

            // user
            QUIAjax.get('package_quiqqer_order_ajax_frontend_basket_controls_basket', this.$render, {
                'package': 'quiqqer/order',
                basketId : basketId
            });
        },

        /**
         * render the result - set events etc
         */
        $render: function (result) {
            this.getElm().set('html', result);
            this.$Loader.inject(this.getElm());
            this.$setEvents();
            this.$Loader.hide();
        },

        /**
         * set the basket events
         * - article changing
         */
        $setEvents: function () {
            var self = this;

            // remove
            this.getElm().getElements('.fa-trash').addEvent('click', function () {
                self.$Loader.show();

                var Article = this.getParent('.quiqqer-order-basket-small-articles-article');

                // big basket
                if (!Article) {
                    var Node = this;
                    Article  = Node.getParent('.quiqqer-order-basket-articles-article');

                    if (Node.nodeName !== 'BUTTON') {
                        Node = Node.getParent('button');
                    }

                    Node.set('html', '<span class="fa fa-spinner fa-spin"></span>');
                }

                Basket.removeProductPos(Article.get('data-pos')).then(function () {
                    if (self.isInOrder()) {
                        return Basket.toOrder(self.getOrderHash()).then(function () {
                            self.refresh();
                        });
                    }

                    self.refresh();
                });
            });

            //change quantity
            this.getElm().getElements('[name="quantity"]').addEvent('focus', function () {
                this.set('data-quantity', parseInt(this.value));
            });

            this.getElm().getElements('[name="quantity"]').addEvent('blur', function () {
                self.$Loader.show();

                var Article     = this.getParent('.quiqqer-order-basket-small-articles-article');
                var quantity    = this.value;
                var oldQuantity = this.get('data-quantity');

                if (oldQuantity && quantity === oldQuantity) {
                    return;
                }

                // big basket
                if (!Article) {
                    Article = this.getParent('.quiqqer-order-basket-articles-article');
                }

                var pos = Article.get('data-pos');
                this.set('data-quantity', quantity);

                Basket.setQuantity(pos, quantity).then(function () {
                    if (self.isInOrder()) {
                        return Basket.toOrder(self.getOrderHash()).then(function () {
                            self.refresh();
                        });
                    }

                    self.refresh();
                });
            });
        },

        /**
         * Is the user a guest?
         *
         * @return {boolean}
         */
        isGuest: function () {
            return !(QUIQQER_USER && QUIQQER_USER.id);
        },

        /**
         * Is the basket in an order process
         *
         * @return {boolean}
         */
        isInOrder: function () {
            if (this.$isInOrder !== null) {
                return this.$isInOrder;
            }

            this.$isInOrder = !!this.getElm().getParent(
                '[data-qui="package/quiqqer/order/bin/frontend/controls/OrderProcess"]'
            );

            return this.$isInOrder;
        },

        /**
         * Return the order hash if the basket is in the order process
         *
         * @return {String}
         */
        getOrderHash: function () {
            var Node = this.getElm().getParent(
                '[data-qui="package/quiqqer/order/bin/frontend/controls/OrderProcess"]'
            );

            return QUI.Controls.getById(Node.get('data-quiid')).getAttribute('orderHash');
        },

        /**
         * event: on basket refresh
         */
        $onBasketRefresh: function () {
            this.refresh();
        }
    });
});