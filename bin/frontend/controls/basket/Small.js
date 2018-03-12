/**
 * Control for a small basket display
 *
 * @module package/quiqqer/order/bin/frontend/controls/basket/Small
 */
define('package/quiqqer/order/bin/frontend/controls/basket/Small', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax',
    'package/quiqqer/order/bin/frontend/Basket'

], function (QUI, QUIControl, QUIAjax, Basket) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/basket/Basket',

        Binds: [
            '$onInject',
            '$onBasketRefresh'
        ],

        options: {
            basketId: false
        },

        initialize: function (options) {
            var self = this;

            this.parent(options);

            if (!this.getAttribute('basketId')) {
                this.getAttribute('basketId', Basket.getId());
            }

            this.addEvents({
                onInject : this.$onInject,
                onDestroy: function () {
                    Basket.removeEvent('onRefresh', self.$onBasketRefresh);
                }
            });

            Basket.addEvents({
                onRefresh: this.$onBasketRefresh
            });
        },

        /**
         * event: on import
         */
        $onInject: function () {
            this.refresh();
        },

        /**
         * refresh the display
         */
        refresh: function () {
            var self     = this,
                basketId = this.getAttribute('basketId');

            if (basketId === false) {
                self.getElm().set('html', '');
                return Promise.resolve();
            }

            // render the result - set events etc
            var render = function (result) {
                self.getElm().set('html', result);

                var ButtonCheckout = self.getElm().getElement('.to-the-checkout');

                ButtonCheckout.addEvent('mousedown', function (event) {
                    event.stop();
                });

                self.getElm().getElements(
                    '.quiqqer-order-basket-small-articles-article-delete'
                ).addEvent('click', function () {
                    Basket.removeProductPos(
                        this.getParent('.quiqqer-order-basket-small-articles-article').get('data-pos')
                    );
                });

                QUI.parse(self.getElm());
            };

            // guest
            if (this.isGuest()) {
                var products       = [];
                var basketProducts = Basket.getProducts();

                for (var i = 0, len = basketProducts.length; i < len; i++) {
                    products.push(basketProducts[i].getAttributes());
                }

                QUIAjax.get('package_quiqqer_order_ajax_frontend_basket_controls_smallGuest', render, {
                    'package': 'quiqqer/order',
                    products : JSON.encode(products)
                });

                return;
            }

            // user
            QUIAjax.get('package_quiqqer_order_ajax_frontend_basket_controls_small', render, {
                'package': 'quiqqer/order',
                basketId : parseInt(this.getAttribute('basketId'))
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
         * event: on basket refresh
         */
        $onBasketRefresh: function () {
            this.refresh();
        }
    });
});