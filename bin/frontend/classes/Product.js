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

        options: {
            uuid                : false,
            productSetParentUuid: false
        },

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

                data.uniqueId             = this.getUniqueId();
                data.quantity             = this.getQuantity();
                data.uuid                 = this.getUuid();
                data.productSetParentUuid = this.getProductSetParentUuid();
                data.calc                 = calc;

                this.fireEvent('refresh', [this]);

                return data;
            }.bind(this));
        },

        /**
         * @return {String|false}
         */
        getUuid: function () {
            return this.getAttribute('uuid');
        },

        /**
         * @param {String} productSetParentUuid
         * @return {void}
         */
        setProductSetParentUuid: function (productSetParentUuid) {
            this.setAttribute('productSetParentUuid', productSetParentUuid);
        },

        /**
         * @return {String|false}
         */
        getProductSetParentUuid: function () {
            return this.getAttribute('productSetParentUuid');
        },

        /**
         * Return the internal product ID
         *
         * @return {String}
         */
        getUniqueId: function () {
            return this.$uniqueID;
        },

        /**
         * Return the product attributes
         *
         * @returns {Object}
         */
        getAttributes: function () {
            const attributes = this.parent();

            attributes.uuid                 = this.getUuid();
            attributes.productSetParentUuid = this.getProductSetParentUuid();

            return attributes;
        }
    });
});
