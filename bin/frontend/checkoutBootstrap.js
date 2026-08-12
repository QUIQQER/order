(function() {
    'use strict';

    if (window.location.hash !== '#checkout') {
        return;
    }

    window.whenQuiLoaded().then(function() {
        require([
            'package/quiqqer/order/bin/frontend/controls/orderProcess/Window'
        ], function(CheckoutWindow) {
            new CheckoutWindow().open();
        }, function(error) {
            console.error(error);
        });
    }).catch(function(error) {
        console.error(error);
    });
})();
