/**
 * @module package/quiqqer/order/bin/backend/controls/panels/payments/AddPaymentWindow
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/order/bin/backend/controls/panels/payments/AddPaymentWindow', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'package/quiqqer/order/bin/backend/controls/panels/payments/AddPayment',
    'Locale'

], function (QUI, QUIConfirm, AddPayment, QUILocale) {
    "use strict";

    var lg = 'quiqqer/order';

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/order/bin/backend/controls/panels/payments/AddPayment',

        Binds: [
            'submit'
        ],

        options: {
            hash         : false,
            paymentMethod: false
        },

        initialize: function (options) {
            this.setAttributes({
                icon     : 'fa fa-money',
                title    : QUILocale.get(lg, 'panel.btn.paymentBook'),
                maxHeight: 400,
                maxWidth : 600
            });

            this.parent(options);

            this.$AddPayment = null;

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            this.Loader.show();
            this.getContent().set('html', '');

            this.$AddPayment = new AddPayment({
                hash         : this.getAttribute('hash'),
                paymentMethod: this.getAttribute('paymentMethod'),
                events       : {
                    onLoad: function () {
                        this.Loader.hide();
                        this.$AddPayment.focus();
                    }.bind(this),

                    onError: function () {
                        this.close();
                    }.bind(this),

                    onSubmit: this.submit
                }
            }).inject(this.getContent());
        },

        /**
         * Submit the window
         *
         * @return {Promise}
         */
        submit: function () {
            return new Promise(function (resolve) {
                var values = this.$AddPayment.getValue();

                if (values.amount === '') {
                    return;
                }

                if (values.payment_method === '') {
                    return;
                }

                this.fireEvent('submit', [this, values]);
                resolve(values);

                this.close();
            }.bind(this));
        }
    });
});