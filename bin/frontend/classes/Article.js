/**
 * @module package/quiqqer/order/bin/frontend/classes/Article
 * @author www.pcsg.de (Henning Leutz)
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 * @require package/quiqqer/products/bin/classes/Product
 *
 * @event onChange
 * @event onRefresh
 */
define('package/quiqqer/order/bin/frontend/classes/Article', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax',
    'package/quiqqer/products/bin/classes/frontend/Product'

], function (QUI, QUIDOM, Ajax, Product) {
    "use strict";

    return new Class({

        Extends: Product,
        Type   : 'package/quiqqer/order/bin/frontend/classes/Article',

        initialize: function (options) {
            this.parent(options);

            this.$uniqueID = String.uniqueID();
        },

        /**
         * Refresh the article data
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

                data.wid      = this.getWatchListId();
                data.quantity = this.getQuantity();
                data.calc     = calc;

                this.fireEvent('refresh', [this]);

                return data;
            }.bind(this));
        },

        /**
         * Return the internal watchlist ID
         *
         * @return {String}
         */
        getWatchListId: function () {
            return this.$uniqueID;
        }
    });
});
