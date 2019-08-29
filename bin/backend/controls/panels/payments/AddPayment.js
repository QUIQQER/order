/**
 * @module package/quiqqer/order/bin/backend/controls/panels/payments/AddPayment
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onLoad [self]
 */
define('package/quiqqer/order/bin/backend/controls/panels/payments/AddPayment', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/utils/Form',
    'Mustache',
    'Locale',
    'package/quiqqer/payments/bin/backend/Payments',
    'package/quiqqer/order/bin/backend/Orders',

    'text!package/quiqqer/order/bin/backend/controls/panels/payments/AddPayment.html'

], function (QUI, QUIControl, QUIFormUtils, Mustache, QUILocale, Payments, Orders, template) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/backend/controls/panels/payments/AddPayment',

        Binds: [
            '$onInject'
        ],

        options: {
            hash         : false,
            paymentMethod: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$Form = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Create the DomNode Element
         *
         * @return {Element|null}
         */
        create: function () {
            this.$Elm = new Element('div', {
                html: Mustache.render(template, {
                    amountTitle : QUILocale.get('quiqqer/order', 'dialog.add.payment.amount'),
                    paymentTitle: QUILocale.get('quiqqer/order', 'dialog.add.payment.paymentMethod'),
                    dateTitle   : QUILocale.get('quiqqer/order', 'dialog.add.payment.date')
                })
            });

            this.$Form = this.$Elm.getElement('form');

            this.$Form.addEvent('submit', function (event) {
                event.stop();

                this.fireEvent('submit');
            }.bind(this));

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            Promise.all([
                Payments.getPayments(),
                Orders.get(this.getAttribute('hash'))
            ]).then(function (result) {
                var title, payment;

                var payments = result[0],
                    Order    = result[1],
                    current  = QUILocale.getCurrent();

                var Payments  = this.getElm().getElement('[name="payment_method"]');
                var DateInput = this.getElm().getElement('[name="date"]');
                var Amount    = this.getElm().getElement('[name="amount"]');

                Amount.value = Order.paidStatus.toPay;

                for (var i = 0, len = payments.length; i < len; i++) {
                    payment = payments[i];
                    title   = payment.title;

                    if (typeOf(payment.title) === 'object' && current in payment.title) {
                        title = payment.title[current];
                    }

                    if (typeOf(payment.workingTitle) === 'object' &&
                        current in payment.workingTitle &&
                        payment.workingTitle[current] !== ''
                    ) {
                        title = payment.workingTitle[current];
                    }

                    new Element('option', {
                        html : title,
                        value: parseInt(payment.id)
                    }).inject(Payments);
                }

                if (this.getAttribute('paymentMethod')) {
                    Payments.value = this.getAttribute('paymentMethod');
                }

                DateInput.valueAsDate = new Date();

                this.fireEvent('load', [this]);
            }.bind(this)).catch(function (e) {
                this.fireEvent('error', [this, e]);
            }.bind(this));
        },

        /**
         * Return the form data
         *
         * @return {Object}
         */
        getValue: function () {
            return QUIFormUtils.getFormData(this.$Form);
        },

        /**
         * Focus the amount field
         */
        focus: function () {
            this.getElm().getElement('[name="amount"]').focus();
        }
    });
});