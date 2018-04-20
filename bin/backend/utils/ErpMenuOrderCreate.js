/**
 * @module package/quiqqer/order/bin/backend/Orders/ErpMenuOrderCreate
 */
define('package/quiqqer/order/bin/backend/utils/ErpMenuOrderCreate', function () {
    "use strict";

    return function () {
        return new Promise(function (resolve) {
            require(['package/quiqqer/order/bin/backend/controls/panels/Orders'], function (Orders) {
                new Orders().$clickCreateOrder();
                resolve();
            });
        });
    };
});
