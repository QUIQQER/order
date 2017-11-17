/**
 * @module package/quiqqer/order/bin/frontend/controls/order/Window
 * @author www.pcsg.de (Henning Leutz)
 *
 *
 */
define('package/quiqqer/order/bin/frontend/controls/order/Window', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Confirm',
    'package/quiqqer/watchlist/bin/controls/frontend/Watchlist',
    'package/quiqqer/order/bin/frontend/controls/OrderProcess',
    'Locale',
    'Mustache',

    'text!package/quiqqer/order/bin/frontend/controls/order/Window.html',
    'css!package/quiqqer/order/bin/frontend/controls/order/Window.css'

], function (QUI, QUIControl, QUIButton, QUIConfirm,
             WatchlistControl, Ordering, QUILocale, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/order';

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/order/bin/frontend/controls/order/Window',

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
                var step = OrderProcess.getAttribute('current');
                var data = OrderProcess.getCurrentStepData();

                self.$OrderTitle.set('html', data.title);
                self.$OrderIcon.className = 'fa ' + data.icon;

                if (step === 'checkout') {
                    self.$Next.hide();
                    self.$Submit.show();
                    return;
                }

                self.$Next.show();
                self.$Submit.hide();
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

                    onChange: onOrderChange
                }
            }).inject(this.$Container);

            // buttons
            this.$Previous = new QUIButton({
                text     : QUILocale.get(lg, 'ordering.btn.previous'),
                textimage: 'fa fa-angle-left',
                events   : {
                    onClick: function () {
                        if (this.$Order) {
                            this.$Order.previous();
                        }
                    }.bind(this)
                }
            });

            this.$Next = new QUIButton({
                text     : QUILocale.get(lg, 'ordering.btn.next'),
                textimage: 'fa fa-angle-right',
                events   : {
                    onClick: function () {
                        if (this.$Order) {
                            this.$Order.next();
                        }
                    }.bind(this)
                }
            });

            this.$Submit = new QUIButton({
                'class'  : 'quiqqer-order-btn-submit',
                text     : QUILocale.get(lg, 'ordering.btn.pay.to.order'),
                textimage: 'fa fa-shopping-cart',
                events   : {
                    onClick: function () {
                        if (this.$Order) {
                            this.$Order.next();
                        }
                    }.bind(this)
                }
            });

            this.$Buttons.set('html', '');

            this.addButton(this.$Previous);
            this.addButton(this.$Next);
            this.addButton(this.$Submit);

            this.$Submit.hide();

            this.$Next.getElm().setStyle('float', 'right');
            this.$Submit.getElm().setStyle('float', 'right');

            this.$Previous.getElm().setStyles({
                'float': 'left',
                width  : 140
            });

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

            this.$Order;
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
        }
    });
});