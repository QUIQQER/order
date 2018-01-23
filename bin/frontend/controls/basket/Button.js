/**
 * @module package/quiqqer/order/bin/frontend/controls/basket/Button
 *
 * @require qui/QUI
 * @require qui/controls/Control
 */
define('package/quiqqer/order/bin/frontend/controls/basket/Button', [

    'qui/QUI',
    'qui/controls/Control',
    'Locale',
    'package/quiqqer/order/bin/frontend/controls/order/Window',

    'css!package/quiqqer/order/bin/frontend/controls/basket/Button.css'

], function (QUI, QUIControl, QUILocale, BasketWindow) {
    "use strict";

    var lg = 'quiqqer/order';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/basket/Button',

        Binds: [
            '$onImport',
            '$onInject',
            'showSmallBasket'
        ],

        options: {
            text         : true,
            styles       : false,
            batchPosition: {
                right: -16,
                top  : -10
            }
        },

        initialize: function (options) {
            this.parent(options);

            this.$Icon = null;
            this.$Text = null;

            this.$BasketSmall     = null;
            this.$BasketContainer = null;
            this.$isLoaded        = false;

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject
            });
        },

        /**
         * Create the domnode element
         *
         * @return {Element|null}
         */
        create: function () {
            var text = QUILocale.get(lg, 'control.basket.button.text');

            this.$Elm = new Element('button', {
                'class': 'quiqqer-order-basketButton button--callToAction',
                'html' : '<span class="quiqqer-order-basketButton-icon fa fa-spinner fa-spin"></span>' +
                '<span class="quiqqer-order-basketButton-text">' + text + '</span>' +
                '<span class="quiqqer-order-basketButton-batch">0</span>'
            });

            if (this.getAttribute('styles')) {
                this.$Elm.setStyles(this.getAttribute('styles'));
            }

            if (this.getAttribute('text') === false) {
                this.$Elm.getElement('.quiqqer-order-basketButton-text').setStyle('display', 'none');
            }

            return this.$Elm;
        },

        /**
         * event: on import
         */
        $onInject: function () {
            this.$onImport();
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this,
                Elm  = this.getElm();

            this.$Icon  = Elm.getElement('.quiqqer-order-basketButton-icon');
            this.$Text  = Elm.getElement('.quiqqer-order-basketButton-text');
            this.$Batch = Elm.getElement('.quiqqer-order-basketButton-batch');

            Elm.addEvent('click', function () {
                new BasketWindow().open();
            });

            Elm.addEvent('mouseenter', this.showSmallBasket);


            this.$Batch.set('html', '<span class="fa fa-spinner fa-spin"></span>');

            var isLoaded = function () {
                if (this.$isLoaded) {
                    return;
                }

                this.$Icon.removeClass('fa-spinner');
                this.$Icon.removeClass('fa-spin');
                this.$Icon.addClass(' fa-file-text-o');

                this.$isLoaded = true;
            }.bind(this);

            require([
                'package/quiqqer/order/bin/frontend/Basket'
            ], function (Basket) {

                Basket.addEvents({
                    onRefresh: function () {
                        isLoaded();
                        self.updateBatch(Basket.getQuantity());
                    },

                    onRefreshBegin: function () {
                        self.$Batch.set('html', '<span class="fa fa-spinner fa-spin"></span>');
                    },

                    onClear: function () {
                        isLoaded();
                        self.updateBatch(Basket.getQuantity());
                    }
                });

                if (Basket.isLoaded()) {
                    isLoaded();

                    self.updateBatch(Basket.getQuantity());
                }
            });
        },

        /**
         * Show the small basket
         */
        showSmallBasket: function () {
            var self   = this,
                pos    = this.getElm().getPosition(),
                height = this.getElm().getSize();

            if (!this.$BasketContainer) {
                this.$BasketContainer = new Element('div', {
                    'class' : 'quiqqer-order-basket-small-container',
                    html    : '<span class="fa fa-spinner fa-spin"></span>',
                    tabindex: -1,
                    events  : {
                        blur: function () {
                            this.setStyle('display', 'none');
                        }
                    }
                }).inject(document.body);
            }

            this.$BasketContainer.setStyles({
                display: null,
                left   : pos.x,
                top    : pos.y + height.y
            });

            if (this.$BasketSmall) {
                this.$BasketSmall.refresh();
                this.$BasketContainer.focus();
                return;
            }

            require([
                'package/quiqqer/order/bin/frontend/Basket',
                'package/quiqqer/order/bin/frontend/controls/basket/Small'
            ], function (Basket, Small) {
                self.$BasketContainer.set('html', '');
                self.$BasketContainer.focus();

                self.$BasketSmall = new Small({
                    basketId: Basket.getId()
                }).inject(self.$BasketContainer);
            });
        },

        /**
         * Update the batch
         */
        updateBatch: function (count) {
            if (!this.$Batch) {
                return Promise.resolve();
            }

            this.$Batch.set('html', QUILocale.getNumberFormatter().format(count));

            if (count) {
                return this.showBatch();
            }

            return this.hideBatch();
        },

        /**
         * Show the batch
         *
         * @returns {Promise}
         */
        showBatch: function () {
            if (!this.$Batch) {
                return Promise.resolve();
            }

            return new Promise(function (resolve) {
                moofx(this.$Batch).animate({
                    opacity: 1,
                    right  : this.$getBatchPosition().right,
                    top    : this.$getBatchPosition().top
                }, {
                    duration: 200,
                    callback: resolve
                });
            }.bind(this));
        },

        /**
         * Hide the batch
         *
         * @returns {Promise}
         */
        hideBatch: function () {
            if (!this.$Batch) {
                return Promise.resolve();
            }

            return new Promise(function (resolve) {
                moofx(this.$Batch).animate({
                    opacity: 0,
                    right  : this.$getBatchPosition().right,
                    top    : 0
                }, {
                    duration: 200,
                    callback: resolve
                });
            }.bind(this));
        },

        /**
         * Return the batch position parameter
         *
         * @returns {{top: number, right: number}}
         */
        $getBatchPosition: function () {
            var batchPosition = this.getAttribute('batchPosition'),
                right         = -16,
                top           = -10;

            if ("right" in batchPosition) {
                right = batchPosition.right;
            }

            if ("top" in batchPosition) {
                top = batchPosition.top;
            }

            return {
                top  : top,
                right: right
            };
        }
    });
});