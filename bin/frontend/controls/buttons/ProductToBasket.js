/**
 * @module package/quiqqer/order/bin/frontend/controls/buttons/ProductToBasket
 */
define('package/quiqqer/order/bin/frontend/controls/buttons/ProductToBasket', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/order/bin/frontend/Basket'

], function (QUI, QUIControl, Basket) {
    "use strict";

    return new Class({
        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/buttons/ProductToBasket',


        Binds: [
            '$onImport',
            '$onInject',
            '$addProductToBasket'
        ],

        options: {
            productId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$Input = null;
            this.$Text  = null;

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var Elm = this.getElm(),
                pid = Elm.get('data-pid');

            if (!pid || pid === '') {
                return;
            }

            this.setAttribute('productId', pid);

            this.$Input = Elm.getElement('input');
            this.$Text  = Elm.getElement('.text');

            this.$Input.setStyles({
                zIndex: 10
            });

            this.$Input.addEvent('click', function (event) {
                event.stop();
            });

            Elm.addEvent('click', this.$addProductToBasket);
            Elm.removeClass('disabled');
        },

        /**
         * event: on inject
         */
        $onInject: function () {

        },

        /**
         * add the product to the watchlist
         */
        $addProductToBasket: function () {
console.warn(111);
        }
    });
});