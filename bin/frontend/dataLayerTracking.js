window.whenQuiLoaded().then(function() {
    'use strict';

    require([
        'qui/QUI',
        'Ajax'
    ], function(QUI, QUIAjax) {

        //region helper functions

        /**
         * Sends a request to track the contents of a basket.
         *
         * @param {Basket} Basket - The basket object containing the products to track.
         * @return {Promise} A promise that resolves when the tracking request is completed.
         */
        function getBasketData(Basket)
        {
            if (!parseInt(QUIQQER_USER.id)) {
                return new Promise(function(resolve) {
                    let products = [];
                    let basketProducts = Basket.getProducts();

                    for (let i = 0, len = basketProducts.length; i < len; i++) {
                        products.push(basketProducts[i].getAttributes());
                    }

                    QUIAjax.get('package_quiqqer_order_ajax_frontend_dataLayer_getTrackData', resolve, {
                        'package': 'quiqqer/order',
                        basketId: Basket.getId(),
                        products: JSON.encode(products)
                    });
                });
            }

            return new Promise(function(resolve) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_dataLayer_getTrackData', resolve, {
                    'package': 'quiqqer/order',
                    basketId: Basket.getId()
                });
            });
        }

        /**
         * Tracks a product view in Matomo.
         *
         * @param {boolean|integer} productId - The ID of the product being viewed.
         *
         * @return {void}
         */
        function trackProductView(productId)
        {
            QUIAjax.get('package_quiqqer_order_ajax_frontend_dataLayer_getProductData', function(product) {
                window.qTrack('event', 'view_item', {
                    'currency': product.currency.code,
                    'value': product.price,
                    'items': [
                        {
                            item_id: product.productNo,
                            item_name: product.title,
                            //affiliation: product.manufacturer,
                            //item_brand: 'Google',
                            item_category: product.category,
                            item_variant: product.variant,
                            price: product.price,
                            quantity: 1
                        }
                    ],

                    'page_location': window.location.toString(),
                    'page_title': document.title,
                    'visitor_type': QUIQQER_USER.id ? 'user' : 'visitor',
                    'site_type': QUIQQER_SITE.type.replace('quiqqer/', ''), // <- mor wollte das
                    'site:id': QUIQQER_SITE.id
                });
            }, {
                'package': 'quiqqer/order',
                productId: productId
            });
        }

        /**
         * Tracks a category view
         *
         * @param siteId
         */
        function trackCategoryView(siteId)
        {
            QUIAjax.get('package_quiqqer_order_ajax_frontend_dataLayer_getCategoryData', function(category) {
                window.qTrack('event', 'page_view', {
                    'page_location': window.location.toString(),
                    'page_title': document.title,
                    'visitor_type': QUIQQER_USER.id ? 'user' : 'visitor',
                    'site_type': QUIQQER_SITE.type.replace('quiqqer/', ''), // <- mor wollte das
                    'site_id': QUIQQER_SITE.id,
                    'category': category
                });
            }, {
                'package': 'quiqqer/order',
                siteId: siteId
            });
        }

        function getOrderData(OrderProcess)
        {
            if (typeof OrderProcess === 'string') {
                return new Promise((resolve) => {
                    QUIAjax.get(
                        'package_quiqqer_order_ajax_frontend_dataLayer_getTrackDataForOrderProcess',
                        function(orderData) {
                            orderData.url = window.location.toString();
                            orderData.step = window.location.pathname;
                            resolve(orderData);
                        },
                        {
                            'package': 'quiqqer/order',
                            orderHash: OrderProcess
                        }
                    );
                });
            }

            const stepData = OrderProcess.getCurrentStepData();
            let url = '/' + stepData.step;

            if (QUIQQER_SITE.url !== '' && QUIQQER_SITE.url !== '/') {
                url = QUIQQER_SITE.url + url;
            }

            return new Promise((resolve) => {
                OrderProcess.getOrder().then(function(orderHash) {
                    QUIAjax.get(
                        'package_quiqqer_order_ajax_frontend_dataLayer_getTrackDataForOrderProcess',
                        function(orderData) {
                            orderData.url = url;
                            orderData.step = stepData.step;
                            resolve(orderData);
                        },
                        {
                            'package': 'quiqqer/order',
                            orderHash: orderHash
                        }
                    );
                });
            });
        }

        /**
         * Return current product id
         *
         * @return {boolean|integer}
         */
        function getProductId()
        {
            if (typeof window.QUIQQER_PRODUCT_ID === 'undefined') {
                return false;
            }

            return window.QUIQQER_PRODUCT_ID;
        }

        //endregion

        //region events

        // basket tracking only if order is installed
        if (typeof window.QUIQQER_ORDER_ORDER_PROCESS_MERGE !== 'undefined') {
            require(['package/quiqqer/order/bin/frontend/Basket'], function(Basket) {
                Basket.addEvent('onAdd', function() {
                    getBasketData(Basket).then(function(data) {
                        window.qTrack('event', 'add_to_cart', data);
                    });
                });

                Basket.addEvent('onRemove', function() {
                    getBasketData(Basket).then(function(data) {
                        window.qTrack('event', 'view_cart', data);
                    });
                });

                Basket.addEvent('onClear', function(Basket) {
                    getBasketData(Basket).then(function(data) {
                        window.qTrack('event', 'view_cart', data);
                    });
                });
            });
        }


        // category / product tracking
        if (window.QUIQQER_SITE.type === 'quiqqer/products:types/category' && !getProductId()) {
            trackCategoryView(window.QUIQQER_SITE.id);
        }

        if (window.QUIQQER_SITE.type === 'quiqqer/products:types/category' && getProductId()) {
            trackProductView(getProductId());
        }

        QUI.addEvent('onQuiqqerProductsOpenProduct', function(Parent, productId) {
            trackProductView(productId);
        });

        QUI.addEvent('onQuiqqerProductsCloseProduct', function() {
            trackCategoryView(window.QUIQQER_SITE.id);
        });


        QUI.addEvent('onQuiqqerOrderProcessOpenStep', function(OrderProcess) {
            getOrderData(OrderProcess).then((order) => {
                window.qTrack('event', 'view_cart', order);
            });
        });

        QUI.addEvent('onQuiqqerOrderProcessLoad', function(OrderProcess) {
            getOrderData(OrderProcess).then((order) => {
                window.qTrack('event', 'begin_checkout', order);
            });
        });

        QUI.addEvent('onQuiqqerOrderProductAdd', function(OrderProcess) {
            getOrderData(OrderProcess).then((order) => {
                window.qTrack('event', 'add_to_cart', order);
            });
        });

        // finish
        QUI.addEvent('onQuiqqerOrderProcessFinish', function(orderHash) {
            getOrderData(orderHash).then((order) => {
                window.qTrack('event', 'purchase', order);
            });
        });

        if (QUI.getAttribute('QUIQQER_ORDER_CHECKOUT_FINISH')) {
            QUIAjax.get('package_quiqqer_order_ajax_frontend_dataLayer_getTrackDataForOrderProcess', function(order) {
                window.qTrack('event', 'purchase', order);
            }, {
                'package': 'quiqqer/order',
                orderHash: QUI.getAttribute('QUIQQER_ORDER_CHECKOUT_FINISH')
            });
        }
    });
});
