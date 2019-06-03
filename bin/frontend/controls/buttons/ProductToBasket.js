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

            this.$Button         = null; // add to basket button
            this.$Label          = null; // add to basket button text
            this.$Quantity       = null; // quantity input
            this.addingInProcess = false;
            this.changeButtons   = null; // buttons to change quantity -/+

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

            this.$Input = Elm.getElement('input');
            this.$Text  = Elm.getElement('.add-to-basket-text');

            this.$Quantity = Elm.getElement('.quiqqer-order-button-add-quantity');
            this.$Button   = Elm.getElement('.add-to-basket');
            this.$Label    = Elm.getElement('.add-to-basket-text');

            this.changeButtons = Elm.getElements(
                '.quiqqer-order-button-add-quantity-decrease, .quiqqer-order-button-add-quantity-increase'
            );

            this.changeButtons.addEvent('click', function (event) {
                event.stop();
                var Target = event.target;

                if (this.addingInProcess) {
                    return;
                }

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

            if (Elm.get('data-qui-options-disabled')) {
                this.disable();
            } else {
                this.enable();
            }
        },

        /**
         * Disable the button
         */
        disable: function () {
            this.getElm().addClass('disabled');
            this.$Button.set('disabled', true);
            this.$disabled = true;
        },

        /**
         * Enable the button
         */
        enable: function () {
            this.getElm().removeClass('disabled');
            this.$Button.set('disabled', false);
            this.$disabled = false;

            this.$Button.addEvent('click', function () {
                if (this.addingInProcess) {
                    return;
                }

                this.addingInProcess = true;
                this.disableQuantityButton();

                this.$addProductToBasket();
            }.bind(this));
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

            var self  = this,
                count = 0,
                size  = this.getElm().getSize();

            if (this.$Input) {
                count = parseInt(this.$Input.value);
            }

            if (!count) {
                count = 0;
            }

            this.$Label.setStyle('visibility', 'hidden');

            var Loader = new Element('div', {
                'class': 'quiqqer-order-button-add-loader',
                html   : '<span class="fa fa-spinner fa-spin"></span>',
                styles : {
                    height        : '100%',
                    left          : 0,
                    position      : 'absolute',
                    top           : 0,
                    width         : '100%',
                    display       : 'flex',
                    alignItems    : 'center',
                    justifyContent: 'center'
                }
            }).inject(this.$Button);

            var Product = new BasketProduct({
                id: this.getAttribute('productId')
            });

            // is the button in a product?
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
                    self.enableQuantityButton();
                    self.$Label.setStyle('visibility', 'visible');

                    moofx(Loader).animate({
                        opacity: 0
                    }, {
                        duration: 300,
                        callback: function () {
                            self.addingInProcess = false;
                            Loader.destroy();
                        }
                    });

                    self.getElm().removeClass('disabled');

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
        },

        /**
         * Disable buttons to change quantity
         */
        disableQuantityButton: function () {
            this.$Quantity.setStyle('opacity', 0.5);
            this.$Input.setAttribute('disabled', true);
        },

        /**
         * Enable buttons to change quantity
         */
        enableQuantityButton: function () {
            this.$Quantity.setStyle('opacity', '1');
            this.$Input.removeAttribute('disabled');
        }
    });
});