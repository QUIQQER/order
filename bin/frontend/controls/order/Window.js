/**
 * @module package/quiqqer/order/bin/frontend/controls/order/Window
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/windows/Confirm
 * @require package/quiqqer/watchlist/bin/controls/frontend/Watchlist
 * @require package/quiqqer/order/bin/frontend/controls/Ordering
 * @require Locale
 */
define('package/quiqqer/order/bin/frontend/controls/order/Window', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'package/quiqqer/watchlist/bin/controls/frontend/Watchlist',
    'package/quiqqer/order/bin/frontend/controls/Ordering',
    'Locale',
    'Mustache',

    'text!package/quiqqer/order/bin/frontend/controls/order/Window.html',
    'css!package/quiqqer/order/bin/frontend/controls/order/Window.css'

], function (QUI, QUIControl, QUIConfirm, WatchlistControl, Ordering, QUILocale, Mustache, template) {
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

            this.$Order     = null;
            this.$Header    = null;
            this.$Container = null;

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

            var Content = this.getContent();

            Content.set({
                html  : Mustache.render(template, {
                    title: 'Bestellung'
                }),
                styles: {
                    padding: 0
                }
            });

            Content.addClass('quiqqer-order-window');

            this.$Container = Content.getElement('.quiqqer-order-window-container');
            this.$Header    = Content.getElement('.quiqqer-order-window-header');

            this.$Order = new Ordering({
                buttons: false,
                events : {
                    onLoad: function () {
                        this.Loader.hide();
                    }.bind(this)
                }
            }).inject(this.$Container);

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