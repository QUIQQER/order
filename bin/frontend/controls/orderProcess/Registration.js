/**
 * @module package/quiqqer/order/bin/frontend/controls/orderProcess/Registration
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/order/bin/frontend/controls/orderProcess/Registration', [

    'qui/QUI',
    'qui/controls/Control'

], function (QUI, QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/orderProcess/Registration',

        Binds: [
            '$onImport'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {

            // login
            var LoginElm  = this.getElm().getElement('[data-qui="controls/users/auth/QUIQQERLogin"]');
            var loginInit = function () {
                var Login = QUI.Controls.getById(LoginElm.get('data-quiid'));

                console.warn(Login);
            };

            if (LoginElm.get('data-quiid')) {
                loginInit();
            } else {
                LoginElm.addEvent('load', loginInit);
            }

            // registration

        }
    });
});
