/**
 * @module package/quiqqer/order/bin/frontend/controls/buttons/ProductToBasket
 */
define('package/quiqqer/order/bin/frontend/controls/buttons/ProductToBasket', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/order/bin/frontend/Basket',
    'package/quiqqer/order/bin/frontend/classes/Article'

], function (QUI, QUIControl, Basket, BasketArticle) {
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

            this.$Input = null;
            this.$Text  = null;

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
            this.$Text  = Elm.getElement('.text');

            this.$Input.setStyles({
                zIndex: 10
            });

            this.$Input.addEvent('click', function (event) {
                event.stop();
            });

            Elm.addEvent('click', this.$addProductToBasket);
            Elm.removeClass('disabled');
        },

        /**
         * event: on inject
         */
        $onInject: function () {

        },

        /**
         * add the product to the watchlist
         */
        $addProductToBasket: function () {
            this.getElm().addClass('disabled');

            this.$Text.setStyles({
                visibility: 'hidden'
            });

            var self  = this,
                count = 0,
                size  = this.getElm().getSize();

            if (this.$Input) {
                this.$Input.setStyles({
                    opacity   : 0,
                    visibility: 'hidden'
                });

                count = parseInt(this.$Input.value);
            }

            if (!count) {
                count = 0;
            }

            var Loader = new Element('div', {
                html  : '<span class="fa fa-spinner fa-spin"></span>',
                styles: {
                    fontSize  : (size.y / 3).round(),
                    height    : '100%',
                    left      : 0,
                    lineHeight: size.y,
                    position  : 'absolute',
                    textAlign : 'center',
                    top       : 0,
                    width     : '100%'
                }
            }).inject(this.getElm());

            var Article = new BasketArticle({
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

            Article.setFieldValues(fields).then(function () {
                return Article.setQuantity(count);

            }).then(function () {
                return Basket.addArticle(Article);

            }).then(function () {
                var Span = Loader.getElement('span');

                Span.removeClass('fa-spinner');
                Span.removeClass('fa-spin');

                Span.addClass('success');
                Span.addClass('fa-check');

                (function () {
                    Loader.destroy();

                    self.getElm().removeClass('disabled');

                    if (self.$Input) {

                        self.$Input.setStyle('visibility', null);

                        moofx(self.$Input).animate({
                            opacity: 1
                        });
                    }

                    self.$Text.setStyle('visibility', null);

                    moofx(self.$Text).animate({
                        opacity: 1
                    });

                }).delay(1000);
            }.bind(this));
        }
    });
});