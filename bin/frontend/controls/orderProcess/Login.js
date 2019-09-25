/**
 * @module package/quiqqer/order/bin/frontend/controls/orderProcess/Login
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/order/bin/frontend/controls/orderProcess/Login', [

    'qui/QUI',
    'qui/controls/Control'

], function (QUI, QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/orderProcess/Login',

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
            var self = this;

            this.getSignUpControl().then(function (Signup) {
                if (!Signup) {
                    return;
                }

                self.getMailRegisterNode(Signup).then(function (MailRegister) {
                    MailRegister.set('data-no-blur-check', 1);
                });
            });
        },

        /**
         * @return {Promise|*}
         */
        getSignUpControl: function () {
            var SignUp = this.getElm().getElement('.quiqqer-fu-registrationSignUp');

            if (SignUp.get('data-quiid')) {
                return Promise.resolve(
                    QUI.Controls.getById(SignUp.get('data-quiid'))
                );
            }

            return new Promise(function (resolve) {
                SignUp.addEvent('load', function () {
                    resolve(QUI.Controls.getById(SignUp.get('data-quiid')));
                });
            });
        },

        /**
         * @param Signup
         * @return {Promise}
         */
        getMailRegisterNode: function (Signup) {
            var fetchMailControl = function () {
                var EmailRegister = Signup.getElm().getElement(
                    '.quiqqer-fu-registrationSignUp-registration-email [name="email"]'
                );
                
                if (!EmailRegister) {
                    return Promise.resolve(false);
                }

                return Promise.resolve(EmailRegister);
            };


            if (Signup.isLoaded()) {
                return fetchMailControl();
            }

            return new Promise(function (resolve) {
                Signup.addEvent('onLoaded', function () {
                    fetchMailControl().then(resolve);
                });
            });
        }
    });
});