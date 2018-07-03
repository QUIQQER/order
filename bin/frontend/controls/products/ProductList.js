/**
 * @module package/quiqqer/order/bin/frontend/controls/products/ProductList
 * @author www.pcsg.de (Henning Leutz)
 *
 * List of Products
 *
 * @event onAddBasketProduct [this, productId] - fires if a Product is added to the Basket
 */
define('package/quiqqer/order/bin/frontend/controls/products/ProductList', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax'

], function (QUI, QUIControl, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/products/ProductList',

        options: {
            productIds: []
        },

        Binds: [
            '$onInject',
            '$onImport'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject,
                onImport: this.$onImport
            });
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;

            this.getProducts().then(function (result) {
                self.getElm().set({
                    html: result
                });

                self.$onImport();
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this;

            this.getElm().getElements('.quiqqer-order-productList-product-addToBasket').addEvents({
                click: function (event) {
                    var Target = event.target;

                    if (Target.nodeName !== 'BUTTON') {
                        Target = Target.getParent('button');
                    }

                    Target.set('disabled', true);

                    var Product   = Target.getParent('article');
                    var productId = Product.get('data-product');

                    require(['package/quiqqer/order/bin/frontend/Basket'], function (Basket) {
                        Basket.addProduct(productId, 1);
                        self.fireEvent('addBasketProduct', [self, productId]);

                        Target.set('disabled', false);
                    });
                }
            });
        },

        /**
         * Return the products
         *
         * @return {Promise}
         */
        getProducts: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_products_get', resolve, {
                    'package' : 'quiqqer/order',
                    productIds: JSON.encode(self.getAttribute('productIds')),
                    onError   : reject
                });
            });
        }
    });
});
