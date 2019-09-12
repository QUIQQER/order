/**
 * @modue package/quiqqer/order/bin/backend/controls/panels/order/Payments
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onLoad [self]
 */
define('package/quiqqer/order/bin/backend/controls/panels/order/Payments', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'controls/grid/Grid',
    'package/quiqqer/order/bin/backend/Orders',
    'package/quiqqer/payment-transactions/bin/backend/Transactions',
    'Locale',
    'Ajax'

], function (QUI, QUIControl, QUIConfirm, Grid, Orders, Transactions, QUILocale, QUIAjax) {
    "use strict";

    var lg = 'quiqqer/order';

    return new Class({

        Type   : 'package/quiqqer/order/bin/backend/controls/panels/order/Payments',
        Extends: QUIControl,

        Binds: [
            '$onInject',
            'openAddPaymentDialog'
        ],

        options: {
            hash    : false,
            Panel   : false,
            disabled: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$Grid = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Resize the control
         */
        resize: function () {
            this.parent();

            if (!this.$Elm) {
                return;
            }

            this.$Grid.setHeight(this.$Elm.getSize().y);
        },

        /**
         * Refresh the data and the display
         *
         * @return {Promise}
         */
        refresh: function () {
            var self = this;

            return Transactions.getTransactionsByHash(this.getAttribute('hash')).then(function (result) {
                var payments = [];

                for (var i = 0, len = result.length; i < len; i++) {
                    payments.push({
                        date   : result[i].date,
                        amount : result[i].amount,
                        payment: result[i].payment,
                        txid   : result[i].txid
                    });
                }

                return new Promise(function (resolve) {
                    QUIAjax.get('package_quiqqer_order_ajax_backend_payments_format', function (data) {
                        self.$Grid.setData({
                            data: data
                        });

                        self.fireEvent('load', [self]);
                        resolve();
                    }, {
                        'package': 'quiqqer/order',
                        payments : JSON.encode(payments)
                    });
                });
            }).then(function () {
                return Orders.get(self.getAttribute('hash'));
            }).then(function (result) {
                var AddButton = self.$Grid.getButtons().filter(function (Button) {
                    return Button.getAttribute('name') === 'add';
                })[0];

                if (result.paid_status !== 1 && !self.getAttribute('disabled')) {
                    AddButton.enable();
                } else {
                    AddButton.disable();
                }
            });
        },

        /**
         * Creates the DomNode Element
         *
         * @return {Element}
         */
        create: function () {
            var self = this;

            this.$Elm = this.parent();

            this.$Elm.setStyles({
                height: '100%'
            });

            var Container = new Element('div', {
                styles: {
                    height: '100%'
                }
            }).inject(this.$Elm);

            this.$Grid = new Grid(Container, {
                buttons    : [{
                    name    : 'add',
                    text    : QUILocale.get(lg, 'panel.btn.paymentBook'),
                    disabled: true,
                    events  : {
                        onClick: this.openAddPaymentDialog
                    }
                }],
                columnModel: [{
                    header   : QUILocale.get(lg, 'panel.payments.date'),
                    dataIndex: 'date',
                    dataType : 'date',
                    width    : 160
                }, {
                    header   : QUILocale.get(lg, 'panel.payments.amount'),
                    dataIndex: 'amount',
                    dataType : 'string',
                    className: 'journal-grid-amount',
                    width    : 160
                }, {
                    header   : QUILocale.get(lg, 'panel.payments.paymentMethod'),
                    dataIndex: 'payment',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'panel.payments.txid'),
                    dataIndex: 'txid',
                    dataType : 'string',
                    width    : 200
                }]
            });

            this.$Grid.addEvents({
                onDblClick: function () {
                    self.$openTransactionId(
                        self.$Grid.getSelectedData()[0].txid
                    );
                }
            });

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            this.resize();
            this.refresh();
        },

        /**
         * Opens the add payment dialog
         */
        openAddPaymentDialog: function () {
            var self = this;

            var Button = this.$Grid.getButtons().filter(function (Button) {
                return Button.getAttribute('name') === 'add';
            })[0];

            Button.setAttribute('textimage', 'fa fa-spinner fa-spin');

            require([
                'package/quiqqer/order/bin/backend/controls/panels/payments/AddPaymentWindow'
            ], function (AddPaymentWindow) {
                new AddPaymentWindow({
                    hash  : self.getAttribute('hash'),
                    events: {
                        onSubmit: function (Win, data) {
                            Orders.addPaymentToOrder(
                                self.getAttribute('hash'),
                                data.amount,
                                data.payment_method,
                                data.date
                            ).then(function () {
                                Button.setAttribute('textimage', 'fa fa-money');
                                self.refresh();
                            });
                        },

                        onClose: function () {
                            Button.setAttribute('textimage', 'fa fa-money');
                        }
                    }
                }).open();
            });
        },

        /**
         * opens a transaction window
         *
         * @param {String} txid - Transaction ID
         */
        $openTransactionId: function (txid) {
            var self = this;

            if (this.getAttribute('Panel')) {
                this.getAttribute('Panel').Loader.show();
            }


            require([
                'package/quiqqer/payment-transactions/bin/backend/controls/windows/Transaction'
            ], function (Window) {
                if (self.getAttribute('Panel')) {
                    self.getAttribute('Panel').Loader.hide();
                }

                new Window({
                    txid: txid
                }).open();
            });
        }
    });
});