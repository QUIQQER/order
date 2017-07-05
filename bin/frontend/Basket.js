/**
 * @module package/quiqqer/order/bin/frontend/Basket
 * @require package/quiqqer/order/bin/frontend/classes/Basket
 */
define('package/quiqqer/order/bin/frontend/Basket', [
    'package/quiqqer/order/bin/frontend/classes/Basket'
], function (Basket) {
    "use strict";
    var GlobalBasket = new Basket();
    GlobalBasket.load();
    
    return GlobalBasket;
});
