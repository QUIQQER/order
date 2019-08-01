/**
 * @module package/quiqqer/order/bin/frontend/Basket
 * @require package/quiqqer/order/bin/frontend/classes/Basket
 */
define('package/quiqqer/order/bin/frontend/Basket', [

    'qui/QUI',
    'package/quiqqer/order/bin/frontend/classes/Basket',
    'Locale',
    'Ajax'

], function (QUI, Basket, QUILocale, QUIAjax) {
    "use strict";

    // storage test
    var storageData     = QUI.Storage.get('quiqqer-basket-products');
    var storageProducts = [];

    try {
        storageData     = JSON.decode(storageData);
        var currentList = storageData.currentList;

        if (typeof storageData.products !== 'undefined' &&
            typeof storageData.products[currentList] !== 'undefined') {
            storageProducts = storageData.products[currentList];
        }
    } catch (e) {
        // nothing
    }

    var lg           = 'quiqqer/order';
    var GlobalBasket = new Basket();

    if (QUIQQER_USER && QUIQQER_USER.id && storageProducts.length) {
        var showWindow = function () {
            // ask user to merge
            require(['qui/controls/windows/Confirm'], function (QUIConfirm) {
                new QUIConfirm({
                    icon         : 'fa fa-file-text-o',
                    texticon     : 'fa fa-file-text',
                    text         : QUILocale.get(lg, 'basket.merge.title'),
                    title        : QUILocale.get(lg, 'basket.merge.title'),
                    information  : QUILocale.get(lg, 'basket.merge.text'),
                    maxHeight    : 400,
                    maxWidth     : 600,
                    cancel_button: {
                        text     : QUILocale.get(lg, 'basket.merge.button.cancel'),
                        textimage: 'fa fa-remove'
                    },
                    ok_button    : {
                        text     : QUILocale.get(lg, 'basket.merge.button.merge'),
                        textimage: 'fa fa-check'
                    },
                    events       : {
                        onSubmit: function () {
                            GlobalBasket.setAttribute('mergeLocalStorage', true);
                            GlobalBasket.load();
                        },

                        onCancel: function () {
                            GlobalBasket.setAttribute('mergeLocalStorage', false);
                            GlobalBasket.load();
                        }
                    }
                }).open();
            });
        };

        GlobalBasket.getBasket().then(function (basket) {
            var products = basket.products;

            // if there are no products yet, merge without query
            if (!products.length) {
                GlobalBasket.setAttribute('mergeLocalStorage', true);
                GlobalBasket.load();
                return;
            }

            showWindow();
        });
    } else {
        GlobalBasket.load();
    }

    return GlobalBasket;
});
