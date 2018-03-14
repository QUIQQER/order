/**
 * @module package/quiqqer/order/bin/frontend/controls/order/Window
 * @author www.pcsg.de (Henning Leutz)
 *
 * Shows a specific order in a qui window
 */
define('package/quiqqer/order/bin/frontend/controls/order/Window', [

    'qui/QUI',
    'qui/controls/windows/Popup'

], function (QUI, QUIPopup) {
    "use strict";

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/quiqqer/order/bin/frontend/controls/order/Window',

        options: {
            hash     : false,
            maxHeight: 600,
            maxWidth : 800
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            this.Loader.show();

            var self    = this,
                Content = self.getContent();

            Content.set('html', '');

            require([
                'package/quiqqer/order/bin/frontend/controls/order/Order'
            ], function (Order) {
                new Order({
                    hash  : self.getAttribute('hash'),
                    events: {
                        onLoad: function () {
                            self.Loader.hide();
                        }
                    }
                }).inject(Content);
            });
        }
    });
});