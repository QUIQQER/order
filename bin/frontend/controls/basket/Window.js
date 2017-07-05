/**
 *
 */
define('package/quiqqer/order/bin/frontend/controls/basket/Window', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'package/quiqqer/watchlist/bin/controls/frontend/Watchlist',
    'Locale'

], function (QUI, QUIControl, QUIConfirm, WatchlistControl, QUILocale) {
    "use strict";

    var lg = 'quiqqer/order';

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/order/bin/frontend/controls/basket/Window',

        Binds: [
            '$onOpen',
            '$onClose',
            '$onSubmit'
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

            this.$List = null;

            this.addEvents({
                onOpen  : this.$onOpen,
                onClose : this.$onClose,
                onSubmit: this.$onSubmit
            });
        },

        $onOpen: function () {
            var self = this;

            this.getContent().set({
                html  : '',
                styles: {
                    padding: 0
                }
            });

        },

        /**
         * window event : on submit
         */
        $onSubmit: function () {
            if (!this.$List) {
                return;
            }

            this.$List.openPurchase().then(function (Win) {
                Win.addEvent('cancel', function () {
                    require([
                        'package/quiqqer/watchlist/bin/controls/frontend/Window'
                    ], function (WatchListWindow) {
                        new WatchListWindow().open();
                    });
                }.bind(this));

                this.cancel();
            }.bind(this));
        },

        /**
         * window event : on close
         */
        $onClose: function () {
            if (this.$List) {
                this.$List.destroy();
            }
        }
    });
});