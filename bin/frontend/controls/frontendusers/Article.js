define('package/quiqqer/order/bin/frontend/controls/frontendusers/Article', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax',
    'package/quiqqer/order/bin/frontend/Basket'

], function (QUI, QUIControl, QUIAjax, Basket) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/frontendusers/Article',

        Binds: [
            '$addArticleToBasket'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$ReBuyButton = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var Elm = this.getElm();

            this.$ReBuyButton = Elm.getElement(
                '.quiqqer-order-profile-orders-order-articles-rebuy'
            );

            if (this.$ReBuyButton) {
                this.$ReBuyButton.addEvent('click', this.$addArticleToBasket);
                this.$ReBuyButton.set('disabled', false);
            }
        },

        /**
         * add article to the basket
         */
        $addArticleToBasket: function (event) {
            if (typeof event.stop !== 'undefined') {
                event.stop();
            }

            if (!this.$ReBuyButton) {
                return;
            }

            var self    = this,
                oldText = this.$ReBuyButton.get('html');

            this.$ReBuyButton.set({
                disabled: true,
                styles  : {
                    width: this.$ReBuyButton.getSize().x
                }
            });

            this.$ReBuyButton.set('html', '<span class="fa fa-spinner fa-spin"></span>');

            this.getProductByProductNo(
                this.$ReBuyButton.get('data-articleno')
            ).then(function (product) {
                return Basket.addProduct(product.id);
            }).then(function () {
                self.$ReBuyButton.set({
                    html    : oldText,
                    disabled: false,
                    styles  : {
                        width: null
                    }
                });
            });
        },

        /**
         * Return the product id
         *
         * @param {String} productNo
         */
        getProductByProductNo: function (productNo) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_products_ajax_products_frontend_getProductByProductNo', resolve, {
                    'package': 'quiqqer/products',
                    onError  : reject,
                    productNo: productNo
                });
            });
        }
    });
});