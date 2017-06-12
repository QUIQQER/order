/**
 * @module package/quiqqer/order/bin/backend/classes/Orders
 */
define('package/quiqqer/order/bin/backend/classes/Orders', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'

], function (QUI, QUIDOM, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/quiqqer/order/bin/backend/classes/Orders',

        initialize: function (options) {
            this.parent(options);
        },

        /**
         * Return orders for a grid
         *
         * @param {Object} params - Grid params
         * @returns {Promise}
         */
        getList: function (params) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_backend_list', resolve, {
                    'package': 'quiqqer/order',
                    params   : JSON.encode(params),
                    onError  : reject,
                    showError: false
                });
            });
        }
    });
});