/**
 * @module package/quiqqer/order/bin/frontend/controls/order/Order
 * @author www.pcsg.de (Henning Leutz)
 *
 * Shows a specific order in a qui window
 */
define('package/quiqqer/order/bin/frontend/controls/order/Order', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax'

], function (QUI, QUIControl, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/order/Order',

        options: {
            hash: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$order = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * create the domnode element
         *
         * @return {Element}
         */
        create: function () {
            this.$Elm = new Element('div.quiqqer-order-control-order');


            return this.$Elm;
        },

        /**
         * Return the order data
         *
         * @return {null|object}
         */
        getOrder: function () {
            return this.$order;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;

            QUIAjax.get('package_quiqqer_order_ajax_frontend_order_getOrderControl', function (result) {
                self.$order = result.data;

                self.getElm().set('html', result.html);
                self.fireEvent('load', [self]);
            }, {
                'package': 'quiqqer/order',
                orderHash: this.getAttribute('hash')
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {

        }
    });
});