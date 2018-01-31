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

            if (basketId === false || isNaN(basketId)) {
                self.getElm().set('html', 'Gast Warenkorb');
                return Promise.resolve();
            }

            QUIAjax.get('package_quiqqer_order_ajax_frontend_basket_controls_small', function (result) {
                self.getElm().set('html', result);

                var ButtonCheckout = self.getElm().getElement('.to-the-checkout');

                ButtonCheckout.addEvent('mousedown', function (event) {
                    event.stop();
                });
                //
                // ButtonCheckout.addEvent('click', function (event) {
                //     event.stop();
                //
                //     console.log(123);
                // });

                self.getElm().getElements('.fa-trash').addEvent('click', function () {
                    Basket.removeProductPos(
                        this.getParent('.quiqqer-order-basket-small-articles-article').get('data-pos')
                    );
                });
            }, {
                'package': 'quiqqer/order',
                basketId : parseInt(this.getAttribute('basketId'))
            });
        },

        /**
         * event: on basket refresh
         */
        $onBasketRefresh: function () {
            this.refresh();
        }
    });
});