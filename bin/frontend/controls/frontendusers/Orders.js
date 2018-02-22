/**
 * @module package/quiqqer/order/bin/frontend/controls/frontendusers/Orders
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/order/bin/frontend/controls/frontendusers/Orders', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'Ajax'

], function (QUI, QUIControl, QUILoader, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/frontendusers/Orders',

        Binds: [
            '$addArticleToBasket'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Orders = null;
            this.$List   = null;

            this.Loader = new QUILoader();

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this,
                Elm  = this.getElm();


            this.$List = Elm.getElement('.quiqqer-order-profile-orders-list');

            this.Loader.inject(Elm);

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

            QUIAjax.get('package_quiqqer_order_ajax_frontend_orders_userOrders', function (result) {
                var Ghost = new Element('div', {
                    html: result
                });

                self.$List.set(
                    'html',
                    Ghost.getElement('.quiqqer-order-profile-orders-list').get('html')
                );

                QUI.parse(self.$List).then(function () {
                    self.Loader.hide();
                });
            }, {
                'package': 'quiqqer/order',
                page     : page,
                limit    : limit
            });
        }
    });
});
