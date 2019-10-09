/**
 * @module package/quiqqer/order/bin/frontend/controls/order/Window
 * @author www.pcsg.de (Henning Leutz)
 *
 * Shows a specific order in a qui window
 */
define('package/quiqqer/order/bin/frontend/controls/order/Window', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'Locale'

], function (QUI, QUIPopup, QUILocale) {
    "use strict";

    var lg = 'quiqqer/order';

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/quiqqer/order/bin/frontend/controls/order/Window',

        options: {
            hash           : false,
            maxHeight      : 800,
            maxWidth       : 800,
            icon           : 'fa fa-shopping-basket',
            closeButtonText: QUILocale.get('quiqqer/system', 'close')
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

            require(['package/quiqqer/order/bin/frontend/controls/order/Order'], function (Order) {
                new Order({
                    hash  : self.getAttribute('hash'),
                    events: {
                        onLoad: function (OrderControl) {
                            self.Loader.hide();

                            var orderData = OrderControl.getOrder();

                            if (!orderData) {
                                return;
                            }

                            self.setAttribute(
                                'title',
                                QUILocale.get(lg, 'control.order.window.title', {
                                    orderId: orderData.id
                                })
                            );

                            self.refresh();
                        }
                    }
                }).inject(Content);
            });
        }
    });
});