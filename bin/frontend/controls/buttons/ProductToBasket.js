/**
 * @module package/quiqqer/order/bin/frontend/controls/buttons/ProductToBasket
 */
define('package/quiqqer/order/bin/frontend/controls/buttons/ProductToBasket', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/order/bin/frontend/Basket',
    'package/quiqqer/order/bin/frontend/classes/Product'

], function (QUI, QUIControl, Basket, BasketProduct) {
    "use strict";

    return new Class({
        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/buttons/ProductToBasket',


        Binds: [
            '$onImport',
            '$onInject',
            '$addProductToBasket'
        ],

        options: {
            productId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$Input        = null;
            this.$Text         = null; // Button
            this.changeButtons = null; // buttons to change quantity -/+
            this.$disabled     = false;

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var Elm = this.getElm(),
                pid = Elm.get('data-pid');

            if (!pid || pid === '') {
                return;
            }

            this.setAttribute('productId', pid);

            this.$Input        = Elm.getElement('input');
            this.$Text         = Elm.getElement('.text');
            this.changeButtons = Elm.getElements(
                '.quiqqer-order-button-add-quantity-decrease, .quiqqer-order-button-add-quantity-increase'
            );

            this.changeButtons.addEvent('click', function (event) {
                event.stop();
                var Target = event.target;

                this.changeValue(Target);
            }.bind(this));

            this.$Input.setStyles({
                zIndex: 10
            });

            // set number to input
            this.$Input.addEvent('blur', function () {
                var value = parseInt(this.value);

                if (!value || value < 1) {
                    this.value = 1;
                }
            });

            this.$Text.addEvent('click', this.$addProductToBasket);

            if (Elm.get('data-qui-options-disabled')) {
                this.disable();
            }
        },

        /**
         * Disable the button
         */
        disable: function () {
            this.getElm().addClass('disabled');
            this.$disabled = true;
        },

        /**
         * Enable the button
         */
        enable: function () {
            this.getElm().removeClass('disabled');
            this.$disabled = false;
        },

        /**
         * event: on inject
         */
        $onInject: function () {

        },

        /**
         * add the product to the basket
         */
        $addProductToBasket: function () {
            if (this.$disabled) {
                return;
            }

            this.getElm().addClass('disabled');

            this.$Text.setStyles({
//                visibility: 'hidden'
            });

            var self  = this,
                count = 0,
                size  = this.getElm().getSize();

            if (this.$Input) {
                this.$Input.setStyles({
//                    opacity   : 0,
//                    visibility: 'hidden'
                });

                count = parseInt(this.$Input.value);
            }

            if (!count) {
                count = 0;
            }

            var Loader = new Element('div', {
                'class': 'quiqqer-order-button-add-loader',
                html   : '<span class="fa fa-spinner fa-spin"></span>',
                styles : {
                    fontSize  : (size.y / 3).round(),
                    height    : '100%',
                    left      : 0,
                    lineHeight: size.y,
                    position  : 'absolute',
                    textAlign : 'center',
                    top       : 0,
                    width     : '100%',
                    background: 'rgba(255,255,255,0.65)'
                }
            }).inject(this.getElm());

            var Product = new BasketProduct({
                id: this.getAttribute('productId')
            });

            // is the button in a produkt?
            var fields         = {},
                ProductElm     = this.getElm().getParent('[data-productid]'),
                ProductControl = QUI.Controls.getById(ProductElm.get('data-quiid'));

            if ("getFieldControls" in ProductControl) {
                ProductControl.getFieldControls().each(function (Field) {
                    fields[Field.getFieldId()] = Field.getValue();
                });
            }

            Product.setFieldValues(fields).then(function () {
                return Product.setQuantity(count);
            }).then(function () {
                return Basket.addProduct(Product);
            }).then(function () {
                var Span = Loader.getElement('span');

                Span.removeClass('fa-spinner');
                Span.removeClass('fa-spin');

                Span.addClass('success');
                Span.addClass('fa-check');

                (function () {
                    moofx(Loader).animate({
                        opacity: 0
                    }, {
                        duration: 300,
                        callback: function () {
                            Loader.destroy();
                        }
                    });

                    self.getElm().removeClass('disabled');

                    /*if (self.$Input) {
                        self.$Input.setStyle('visibility', null);

                        moofx(self.$Input).animate({
                            opacity: 1
                        });
                    }*/

//                    self.$Text.setStyle('visibility', null);

                    /*moofx(self.$Text).animate({
                        opacity: 1
                    });*/

                }).delay(1000);
            }.bind(this));
        },

        /**
         * Change value of input depend of button type (decrease / increase)
         *
         * @param Button | DOM Object
         */
        changeValue: function (Button) {
            var type  = Button.getProperty('data-button-type'),
                value = parseInt(this.$Input.value);

            if (!value) {
                value             = 1;
                this.$Input.value = value;
            }

            if (type === 'decrease') {
                if (value < 2) {
                    return;
                }

                this.$Input.value = --value;
                return;
            }

            // increase
            this.$Input.value = ++value;
        }
    });
});