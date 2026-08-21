window.whenQuiLoaded().then(function() {
    'use strict';

    if (typeof window.qTrack !== 'function') {
        return;
    }

    require([
        'qui/QUI',
        'Ajax',
        'package/quiqqer/order/bin/frontend/Basket'
    ], function(QUI, QUIAjax, Basket) {
        function hasItems(data)
        {
            return data && Array.isArray(data.items) && data.items.length > 0;
        }

        function getBasketData(Basket)
        {
            const options = {
                'package': 'quiqqer/order',
                basketId: Basket.getId()
            };

            if (!QUIQQER_USER.isLoggedIn) {
                options.products = JSON.stringify(
                    Basket.getProducts().map(function(Product) {
                        return Product.getAttributes();
                    })
                );
            }

            return new Promise(function(resolve, reject) {
                options.onError = reject;

                QUIAjax.get(
                    'package_quiqqer_order_ajax_frontend_dataLayer_getTrackData',
                    resolve,
                    options
                );
            });
        }

        function getProductsData(Products)
        {
            if (!Array.isArray(Products)) {
                Products = [Products];
            }

            const products = Products.filter(function(Product) {
                return Product && typeof Product.getAttributes === 'function';
            }).map(function(Product) {
                return Product.getAttributes();
            });

            if (!products.length) {
                return Promise.resolve([]);
            }

            return new Promise(function(resolve, reject) {
                QUIAjax.get(
                    'package_quiqqer_order_ajax_frontend_dataLayer_getTrackDataForProducts',
                    resolve,
                    {
                        'package': 'quiqqer/order',
                        products: JSON.stringify(products),
                        onError: reject
                    }
                );
            });
        }

        function trackData(eventName, Data)
        {
            Data.then(function(data) {
                if (hasItems(data)) {
                    window.qTrack('event', eventName, data);
                }
            }).catch(function(error) {
                console.error(error);
            });
        }

        function getOrderData(OrderProcess)
        {
            if (typeof OrderProcess === 'string') {
                return new Promise(function(resolve, reject) {
                    QUIAjax.get(
                        'package_quiqqer_order_ajax_frontend_dataLayer_getTrackDataForOrderProcess',
                        function(orderData) {
                            orderData.url = window.location.toString();
                            orderData.step = window.location.pathname;
                            resolve(orderData);
                        },
                        {
                            'package': 'quiqqer/order',
                            orderHash: OrderProcess,
                            onError: reject
                        }
                    );
                });
            }

            const stepData = OrderProcess.getCurrentStepData();
            let url = '/' + stepData.step;

            if (QUIQQER_SITE.url !== '' && QUIQQER_SITE.url !== '/') {
                url = QUIQQER_SITE.url + url;
            }

            return OrderProcess.getOrder().then(function(orderHash) {
                return new Promise(function(resolve, reject) {
                    QUIAjax.get(
                        'package_quiqqer_order_ajax_frontend_dataLayer_getTrackDataForOrderProcess',
                        function(orderData) {
                            orderData.url = url;
                            orderData.step = stepData.step;
                            resolve(orderData);
                        },
                        {
                            'package': 'quiqqer/order',
                            orderHash: orderHash,
                            onError: reject
                        }
                    );
                });
            });
        }

        function trackPurchase(order)
        {
            if (!hasItems(order) || !order.transaction_id) {
                return;
            }

            const storageKey = 'quiqqer-order-data-layer-purchase-' + order.transaction_id;

            try {
                if (window.sessionStorage.getItem(storageKey)) {
                    return;
                }

                window.sessionStorage.setItem(storageKey, '1');
            } catch (error) {
                console.error(error);
            }

            window.qTrack('event', 'purchase', order);
        }

        if (typeof window.QUIQQER_ORDER_ORDER_PROCESS_MERGE !== 'undefined') {
            QUI.addEvent('onQuiqqerOrderBasketAdd', function(Basket, Product) {
                if (!Basket.isLoaded()) {
                    return;
                }

                trackData('add_to_cart', getProductsData(Product));
            });

            QUI.addEvent('onQuiqqerOrderBasketRemove', function(Basket, Product) {
                trackData('remove_from_cart', getProductsData(Product));
            });

            QUI.addEvent('onQuiqqerOrderBasketClear', function(Basket, Products) {
                trackData('remove_from_cart', getProductsData(Products));
            });

            QUI.addEvent('onQuiqqerOrderBasketView', function(Basket) {
                trackData('view_cart', getBasketData(Basket));
            });
        }

        if (window.QUIQQER_SITE.type === 'quiqqer/order:types/shoppingCart') {
            Basket.ready().then(function() {
                trackData('view_cart', getBasketData(Basket));
            }).catch(function(error) {
                console.error(error);
            });
        }

        QUI.addEvent('onQuiqqerOrderProcessOpenStep', function(OrderProcess, step) {
            if (String(step).toLowerCase() === 'basket') {
                trackData('view_cart', getOrderData(OrderProcess));
            }
        });

        QUI.addEvent('onQuiqqerOrderProcessStepSubmitted', function(OrderProcess, step) {
            switch (String(step).toLowerCase()) {
                case 'basket':
                    trackData('begin_checkout', getOrderData(OrderProcess));
                    break;

                case 'payment':
                    trackData('add_payment_info', getOrderData(OrderProcess));
                    break;

                case 'shipping':
                    trackData('add_shipping_info', getOrderData(OrderProcess));
                    break;
            }
        });

        QUI.addEvent('onQuiqqerOrderProcessFinish', function(orderHash) {
            getOrderData(orderHash).then(trackPurchase).catch(function(error) {
                console.error(error);
            });
        });

        if (QUI.getAttribute('QUIQQER_ORDER_CHECKOUT_FINISH')) {
            QUIAjax.get(
                'package_quiqqer_order_ajax_frontend_dataLayer_getTrackDataForOrderProcess',
                trackPurchase,
                {
                    'package': 'quiqqer/order',
                    orderHash: QUI.getAttribute('QUIQQER_ORDER_CHECKOUT_FINISH'),
                    onError: function(error) {
                        console.error(error);
                    }
                }
            );
        }
    });
});
