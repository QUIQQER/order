/**
 * @module package/quiqqer/order/bin/frontend/Basket
 * @require package/quiqqer/order/bin/frontend/classes/Basket
 */
define('package/quiqqer/order/bin/frontend/Basket', [

    'qui/QUI',
    'qui/controls/buttons/Button',
    'package/quiqqer/order/bin/frontend/classes/Basket',
    'Locale'

], function (QUI, QUIButton, Basket, QUILocale) {
    "use strict";

    // storage test
    var storageData = QUI.Storage.get('quiqqer-basket-products');
    var storageProducts = [];

    try {
        storageData = JSON.decode(storageData);
        var currentList = storageData.currentList;

        if (typeof storageData.products !== 'undefined' &&
            typeof storageData.products[currentList] !== 'undefined') {
            storageProducts = storageData.products[currentList];
        }
    } catch (e) {
        // nothing
    }

    var lg = 'quiqqer/order';
    var GlobalBasket = new Basket();

    // ask user to merge
    GlobalBasket.showMergeWindow = function () {
        return new Promise(function (resolve) {
            require([
                'qui/controls/windows/Confirm',
                'css!package/quiqqer/order/bin/frontend/Basket.css'
            ], function (QUIConfirm) {
                var height = 400,
                    width  = 800;

                if (QUI.getWindowSize().x < 800) {
                    height = QUI.getWindowSize().y;
                    width = QUI.getWindowSize().x;
                }

                new QUIConfirm({
                    icon         : 'fa fa-file-text-o',
                    texticon     : 'fa fa-file-text',
                    text         : QUILocale.get(lg, 'basket.merge.title'),
                    title        : QUILocale.get(lg, 'basket.merge.title'),
                    information  : QUILocale.get(lg, 'basket.merge.text'),
                    maxHeight    : height,
                    maxWidth     : width,
                    autoclose    : false,
                    cancel_button: {
                        text: QUILocale.get(lg, 'basket.merge.button.cancel')
                    },
                    ok_button    : {
                        text: QUILocale.get(lg, 'basket.merge.button.merge')
                    },
                    events       : {
                        onOpen: function (Win) {
                            var Buttons = this.getElm().getElement('.qui-window-popup-buttons');
                            var Submit = Buttons.getElement('[name="submit"]');
                            var Merge = Buttons.getElement('[name="cancel"]');

                            Win.getElm().addClass('window-basket-merge');

                            Submit.addClass('qui-button-cancel');
                            Submit.addClass('btn-light');

                            Submit.removeClass('qui-button-success');
                            Submit.removeClass('btn-success');

                            Merge.addClass('qui-button-cancel');
                            Merge.addClass('btn-light');
                            Merge.name = 'merge';

                            new QUIButton({
                                'class': 'qui-button-success btn-success',
                                text   : QUILocale.get(lg, 'basket.merge.button.use.new'),
                                events : {
                                    onClick: function () {
                                        Win.Loader.show();
                                        GlobalBasket.setAttribute('mergeLocalStorage', 2);
                                        GlobalBasket.load().then(function () {
                                            Win.close();
                                        });
                                    }
                                }
                            }).inject(Buttons);
                        },

                        onSubmit: function (Win) {
                            Win.Loader.show();
                            GlobalBasket.setAttribute('mergeLocalStorage', 1);
                            GlobalBasket.load().then(function () {
                                Win.close();
                            });
                        },

                        onCancel: function (Win) {
                            Win.Loader.show();
                            GlobalBasket.setAttribute('mergeLocalStorage', 0);
                            GlobalBasket.load().then(function () {
                                Win.close();
                            });
                        },

                        onClose: resolve
                    }
                }).open();
            });
        });
    };

    if (QUIQQER_USER && QUIQQER_USER.id && storageProducts.length) {
        GlobalBasket.getBasket().then(function (basket) {
            var products = basket.products;

            // if there are no products yet, merge without query
            if (!products.length) {
                GlobalBasket.setAttribute('mergeLocalStorage', 1);
                GlobalBasket.load().then(function () {
                    if (QUIQQER_SITE.type !== 'quiqqer/order:types/orderingProcess') {
                        return;
                    }

                    const orderProcessNode = document.getElement('[data-qui="package/quiqqer/order/bin/frontend/controls/OrderProcess"]');

                    if (!orderProcessNode) {
                        return;
                    }

                    const getInstance = function (Node) {
                        return new Promise(function (resolve) {
                            if (Node.get('data-quiid')) {
                                resolve(QUI.Controls.getById(Node.get('data-quiid')));
                                return;
                            }

                            Node.addEvent('load', function () {
                                resolve(QUI.Controls.getById(Node.get('data-quiid')));
                            });
                        });
                    };

                    getInstance(orderProcessNode).then(function (Instance) {
                        Instance.reload();
                    });
                });
                return;
            }

            GlobalBasket.showMergeWindow();
        });
    } else {
        GlobalBasket.load();
    }

    return GlobalBasket;
});
