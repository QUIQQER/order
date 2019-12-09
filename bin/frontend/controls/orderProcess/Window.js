/**
 * @module package/quiqqer/order/bin/frontend/controls/orderProcess/Window
 * @author www.pcsg.de (Henning Leutz)
 *
 * Opens the order process in a qui window
 */
define('package/quiqqer/order/bin/frontend/controls/orderProcess/Window', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Confirm',
    'package/quiqqer/order/bin/frontend/controls/OrderProcess',
    'Locale',
    'Mustache',

    'text!package/quiqqer/order/bin/frontend/controls/orderProcess/Window.html',
    'css!package/quiqqer/order/bin/frontend/controls/orderProcess/Window.css'

], function (QUI, QUIControl, QUIButton, QUIConfirm, Ordering, QUILocale, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/order';

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/order/bin/frontend/controls/orderProcess/Window',

        Binds: [
            '$onOpen',
            '$onClose',
            '$onSubmit',
            '$onResize'
        ],

        initialize: function (options) {
            // default
            this.setAttributes({
                title        : false,
                icon         : false,
                maxHeight    : 900,
                maxWidth     : 1200,
                autoclose    : false,
                texticon     : false,
                cancel_button: false,
                ok_button    : {
                    text     : QUILocale.get(lg, ''),
                    textimage: 'fa fa-shopping-cart'
                }
            });

            this.parent(options);

            this.$Order = null;

            // nodes
            this.$Header     = null;
            this.$OrderTitle = null;
            this.$OrderIcon  = null;
            this.$Container  = null;

            // buttons
            this.$Previous = null;
            this.$Next     = null;
            this.$Submit   = null;

            this.addEvents({
                onOpen  : this.$onOpen,
                onClose : this.$onClose,
                onSubmit: this.$onSubmit,
                onResize: this.$onResize
            });
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            this.Loader.show();

            var self    = this,
                Content = this.getContent();

            self.getElm().addClass('quiqqer-order-window');

            Content.set({
                html  : Mustache.render(template, {
                    title: QUILocale.get(lg, 'ordering.title')
                }),
                styles: {
                    padding: 0
                }
            });

            Content.addClass('quiqqer-order-window');

            this.$Container  = Content.getElement('.quiqqer-order-window-container');
            this.$Header     = Content.getElement('.quiqqer-order-window-header');
            this.$OrderIcon  = this.$Header.getElement('.fa');
            this.$OrderTitle = this.$Header.getElement('.quiqqer-order-window-header-text-title');

            var onOrderChange = function (OrderProcess) {
                new window.Fx.Scroll(self.$Container).toTop();

                var step = OrderProcess.getAttribute('current');
                var data = OrderProcess.getCurrentStepData();

                self.$OrderTitle.set('html', data.title);
                self.$OrderIcon.className = 'fa ' + data.icon;

                var BasketEnd = Content.getElement('.quiqqer-order-basket-end');

                if (BasketEnd) {
                    QUI.parse(BasketEnd);
                }

                if (step === 'Checkout') {
                    self.$Next.hide();
                    self.$Next.getElm().setAttribute('style', 'display: none !important');

                    self.$Submit.show();
                    return;
                }

                if (step === 'Finish') {
                    var Parent = null;

                    if (self.$Next && self.$Next.getElm()) {
                        Parent = self.$Next.getElm().getParent();
                    }

                    if (Parent) {
                        Parent.getElements('button').destroy();

                        if (!Parent.getElement('.quiqqer-order-basket-end-finish')) {
                            Parent.getElements('button').setStyle('display', 'none');

                            new QUIButton({
                                'class': 'btn-success quiqqer-order-basket-end-finish',
                                text   : QUILocale.get(lg, 'ordering.btn.backToShop'),
                                events : {
                                    onClick: function () {
                                        self.close();
                                        window.location.reload();
                                    }
                                }
                            }).inject(Parent);
                        }
                    }

                    return;
                }

                if (QUIQQER_USER.id) {
                    self.$Next.show();
                }

                self.$Submit.hide();
                self.$Submit.getElm().setAttribute('style', 'display: none !important');
            };

            this.$Order = new Ordering({
                buttons   : false,
                showLoader: false,
                events    : {
                    onLoaderShow: function () {
                        self.Loader.show();
                    },

                    onLoaderHide: function () {
                        self.Loader.hide();
                    },

                    onLoad: function () {
                        onOrderChange(this.$Order);
                        this.Loader.hide();
                    }.bind(this),

                    onChange: onOrderChange,
                    
                    onInject: function () {
                        
                    }
                }
            }).inject(this.$Container);

            // buttons
            this.$Previous = new QUIButton({
                'class'  : 'btn-light qui-window-popup-buttons-previous',
                text     : QUILocale.get(lg, 'ordering.btn.previous'),
                textimage: 'fa fa-angle-left',
                events   : {
                    onClick: function () {
                        if (this.$Order) {
                            if (self.$Order.getCurrentStepData().step === 'Basket') {
                                self.close();
                                return;
                            }

                            this.$Order.previous().then(function () {
                                self.resize();
                            });
                        }
                    }.bind(this)
                }
            });

            this.$Next = new QUIButton({
                'class'  : 'btn-success qui-window-popup-buttons-next',
                text     : QUILocale.get(lg, 'ordering.btn.next'),
                textimage: 'fa fa-angle-right',
                events   : {
                    onClick: function () {
                        if (this.$Order) {
                            this.$Order.next().then(function () {
                                self.resize();
                            });
                        }
                    }.bind(this)
                }
            });

            this.$Submit = new QUIButton({
                'class'  : 'quiqqer-order-btn-submit btn-success qui-window-popup-buttons-order',
                text     : QUILocale.get(lg, 'ordering.btn.pay.to.order'),
                textimage: 'fa fa-shopping-cart',
                events   : {
                    onClick: function () {
                        if (this.$Order) {
                            this.$Order.next().catch(function (err) {
                                console.log(err);
                            });
                        }
                    }.bind(this)
                }
            });

            this.$Buttons.set('html', '');

            this.addButton(this.$Previous);
            this.addButton(this.$Next);
            this.addButton(this.$Submit);

            this.$Submit.hide();
            this.$Submit.getElm().setAttribute('style', 'display: none !important');

            this.$Next.getElm().setStyle('float', 'right');
            this.$Submit.getElm().setStyle('float', 'right');

            this.$Previous.getElm().setStyles({
                'float' : 'left',
                minWidth: 140
            });

            if (!QUIQQER_USER.id) {
                this.$Previous.hide();
                this.$Next.hide();

                this.getContent().setStyle('overflow-x', 'hidden');
                this.$Container.setStyle('overflow-x', 'hidden');

                this.setAttribute('buttons', false);

                this.getElm().getElement('.qui-window-popup-buttons').setStyle('display', 'none');
            }

            Content.getElement('.quiqqer-order-window-header-close').addEvent('click', function () {
                self.close();
            });

            this.resize();

            this.$Container.setStyle('display', 'inline');

            moofx(this.$Container).animate({
                opacity: 1
            });
        },

        /**
         * window event : on submit
         */
        $onSubmit: function () {
            if (!this.$Order) {
                return;
            }

            // this.$Order;
        },

        /**
         * event: on resize
         */
        $onResize: function () {
            var Content = this.getContent(),
                size    = Content.getSize();

            if (!this.$Container || !this.$Header) {
                return;
            }

            this.$Container.setStyle(
                'height',
                size.y - this.$Header.getSize().y
            );
        },

        /**
         * window event : on close
         */
        $onClose: function () {
            if (this.$Order) {
                this.$Order.destroy();
            }

            if (window.location.hash === '#checkout') {
                window.location.hash = '';
            }
        }
    });
});