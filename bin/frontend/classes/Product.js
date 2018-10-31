/**
 * @module package/quiqqer/order/bin/frontend/classes/Product
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onChange
 * @event onRefresh
 */
define('package/quiqqer/order/bin/frontend/classes/Product', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax',
    'package/quiqqer/products/bin/classes/frontend/Product'

], function (QUI, QUIDOM, Ajax, Product) {
    "use strict";

    return new Class({

        Extends: Product,
        Type   : 'package/quiqqer/order/bin/frontend/classes/Product',

        initialize: function (options) {
            this.parent(options);

            this.$uniqueID = String.uniqueID();

            if (!("fields" in options) || !("quantity" in options)) {
                return;
            }

            this.$data     = options;
            this.$loaded   = true;
            this.$quantity = options.quantity;
            this.$fields   = options.fields;
        },

        /**
         * Refresh the product data
         *
         * @returns {Promise}
         */
        refresh: function () {
            return Promise.all([
                this.parent(),
                this.getPrice(this.getQuantity())
            ]).then(function (result) {
                var data = result[0];
                var calc = result[1];

                data.uniqueId = this.getUniqueId();
                data.quantity = this.getQuantity();
                data.calc     = calc;

                this.fireEvent('refresh', [this]);

                return data;
            }.bind(this));
        },

        /**
         * Return the internal product ID
         *
         * @return {String}
         */
        getUniqueId: function () {
            return this.$uniqueID;
        }
    });
});
