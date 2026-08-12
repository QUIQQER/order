/**
 * Return the URL of the order process.
 *
 * @module package/quiqqer/order/bin/frontend/OrderProcessUrl
 */
define('package/quiqqer/order/bin/frontend/OrderProcessUrl', [
    'Ajax'
], function (QUIAjax) {
    'use strict';

    return function () {
        return new Promise(function (resolve, reject) {
            QUIAjax.get('package_quiqqer_order_ajax_frontend_basket_getOrderProcessUrl', resolve, {
                'package': 'quiqqer/order',
                onError: reject,
                showError: false
            });
        });
    };
});
