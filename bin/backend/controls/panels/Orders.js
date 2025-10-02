/**
 * @module package/quiqqer/order/bin/backend/controls/panels/Orders
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/order/bin/backend/controls/panels/Orders', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/Select',
    'qui/controls/windows/Confirm',
    'qui/controls/contextmenu/Item',

    'controls/grid/Grid',
    'package/quiqqer/order/bin/backend/Orders',
    'package/quiqqer/erp/bin/backend/controls/elements/TimeFilter',
    'package/quiqqer/order/bin/backend/ProcessingStatus',
    'Locale',
    'Ajax',
    'Mustache',
    'Packages',

    'text!package/quiqqer/order/bin/backend/controls/panels/Orders.Total.html',

    'css!package/quiqqer/order/bin/backend/controls/panels/Orders.css',
    'css!package/quiqqer/erp/bin/backend/payment-status.css'

], function(QUI, QUIPanel, QUIButton, QUISelect, QUIConfirm, QUIContextMenuItem,
    Grid, Orders, TimeFilter, ProcessingStatus, QUILocale, QUIAjax,
    Mustache, QUIPackages, templateTotal
) {
    'use strict';

    const lg = 'quiqqer/order';
    let shippingInstalled = false;

    return new Class({

        Extends: QUIPanel,
        Type: 'package/quiqqer/order/bin/backend/controls/panels/Orders',

        Binds: [
            'refresh',
            'toggleTotal',
            '$onCreate',
            '$onDestroy',
            '$onResize',
            '$onInject',
            '$clickCreateOrder',
            '$clickCopyOrder',
            '$clickDeleteOrder',
            '$clickOpenOrder',
            '$refreshButtonStatus',
            '$onOrderChange',
            '$onClickOrderDetails',
            '$clickCreateInvoice',
            '$onSearchKeyUp',
            '$onAddPaymentButtonClick',
            '$clickCreateSalesOrder',
            '$onPDFExportButtonClick'
        ],

        initialize: function(options) {
            this.parent(options);

            this.setAttributes({
                icon: 'fa fa-shopping-cart',
                title: QUILocale.get(lg, 'orders.panel.title')
            });

            this.$Grid = null;
            this.$Total = null;
            this.$TimeFilter = null;
            this.$Search = null;
            this.$Currency = null;
            this.$Actions = null;
            this.$Status = null;
            this.$installed = {};  // list of installed packages related to orders

            this.$currentSearch = '';
            this.$searchDelay = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onDestroy: this.$onDestroy,
                onResize: this.$onResize,
                onInject: this.$onInject
            });

            Orders.addEvents({
                onOrderCreate: this.$onOrderChange,
                onOrderDelete: this.$onOrderChange,
                onOrderSave: this.$onOrderChange,
                onOrderCopy: this.$onOrderChange
            });
        },

        /**
         * Refresh the grid
         *
         * @return {Promise}
         */
        refresh: function() {
            if (!this.$Grid) {
                return Promise.resolve();
            }

            const self = this;

            this.Loader.show();

            // db mapping
            let sortOn = self.$Grid.options.sortOn;

            switch (sortOn) {
                case 'customer_id_view':
                case 'customer_id':
                    sortOn = 'customerId';
                    break;
            }

            this.$currentSearch = this.$Search.value;
            this.$Grid.setAttribute('exportName', this.$TimeFilter.$Select.$placeholderText);

            let status = '',
                from = '',
                to = '';

            if (this.$currentSearch !== '') {
                this.disableFilter();
            } else {
                this.enableFilter();

                status = this.$Status.getValue();
                from = this.$TimeFilter.getValue().from;
                to = this.$TimeFilter.getValue().to;
            }


            return Orders.search({
                perPage: this.$Grid.options.perPage,
                page: this.$Grid.options.page,
                sortBy: this.$Grid.options.sortBy,
                sortOn: sortOn
            }, {
                from: from,
                to: to,
                search: this.$currentSearch,
                currency: this.$Currency.getAttribute('value'),
                status: status
            }).then(function(result) {
                const gridData = result.grid;

                gridData.data = gridData.data.map(function(entry) {
                    entry.opener = '&nbsp;';

                    entry.status = new Element('span', {
                        'class': 'order-status',
                        text: entry.status_title,
                        styles: {
                            color: entry.status_color !== '---' ? entry.status_color : '',
                            borderColor: entry.status_color !== '---' ? entry.status_color : ''
                        }
                    });

                    if (shippingInstalled) {
                        entry.shipping_status = new Element('span', {
                            'class': 'order-shipping-status',
                            html: entry.shipping_status_title,
                            styles: {
                                color: entry.shipping_status_color !== '---' ? entry.shipping_status_color : '',
                                borderColor: entry.shipping_status_color !== '---' ? entry.shipping_status_color : ''
                            }
                        });
                    }

                    if (typeof entry.customer_no !== 'undefined') {
                        entry.customer_id_view = entry.customer_no;
                    }

                    return entry;
                });

                self.$Grid.setData(gridData);
                self.$refreshButtonStatus();

                self.$Total.set(
                    'html',
                    Mustache.render(templateTotal, result.total)
                );

                self.Loader.hide();
            }).catch(function(Err) {
                if ('getMessage' in Err) {
                    console.error(Err.getMessage());
                    return;
                }

                console.error(Err);
            });
        },

        /**
         * refresh the button status
         * disabled or enabled
         */
        $refreshButtonStatus: function() {
            if (!this.$Grid) {
                return;
            }

            const selected = this.$Grid.getSelectedData();
            const Pdf = this.$Grid.getButton('printPdf');

            if (selected.length) {
                if (selected[0].paid_status === 1 ||
                    selected[0].paid_status === 5) {
                }

                this.$Actions.enable();
                Pdf.enable();
                return;
            }

            Pdf.disable();
            this.$Actions.disable();
        },

        /**
         * event : on create
         */
        $onCreate: function() {
            const self = this;

            // panel buttons
            this.getContent().setStyles({
                padding: 10
            });

            this.addButton({
                name: 'total',
                text: QUILocale.get(lg, 'panel.btn.total'),
                textimage: 'fa fa-calculator',
                events: {
                    onClick: this.toggleTotal
                }
            });

            // currency
            this.$Currency = new QUIButton({
                name: 'currency',
                disabled: true,
                showIcons: false,
                events: {
                    onChange: function(Menu, Item) {
                        self.$Currency.setAttribute('value', Item.getAttribute('value'));
                        self.$Currency.setAttribute('text', Item.getAttribute('value'));
                        self.refresh();
                    }
                }
            });

            this.addButton(this.$Currency);


            this.$Status = new QUISelect({
                disabled: true,
                showIcons: false,
                styles: {
                    'float': 'right'
                },
                events: {
                    onChange: this.refresh
                }
            });

            this.$Status.appendChild(
                QUILocale.get(lg, 'filter.status.all'),
                ''
            );

            ProcessingStatus.getList().then(function(list) {
                const data = list.data;

                for (let i = 0, len = data.length; i < len; i++) {
                    self.$Status.appendChild(
                        QUILocale.get(lg, 'filter.status', {
                            status: data[i].title
                        }),
                        data[i].hash
                    );
                }

                self.$Status.setValue('');
                self.$Status.addEvent('change', self.refresh);
            });


            this.addButton(this.$Status);

            this.addButton({
                type: 'separator',
                styles: {
                    'float': 'right'
                }
            });

            this.$TimeFilter = new TimeFilter({
                name: 'timeFilter',
                styles: {
                    'float': 'right'
                },
                events: {
                    onChange: this.refresh
                }
            });

            this.addButton(this.$TimeFilter);

            this.addButton({
                type: 'separator',
                styles: {
                    'float': 'right'
                }
            });

            this.addButton({
                name: 'search',
                icon: 'fa fa-search',
                styles: {
                    'float': 'right'
                },
                events: {
                    onClick: this.refresh
                }
            });

            this.$Search = new Element('input', {
                type: 'search',
                placeholder: 'Search...', // #locale
                styles: {
                    'float': 'right',
                    margin: '10px 0 0 0',
                    width: 200
                },
                events: {
                    keyup: this.$onSearchKeyUp,
                    search: this.$onSearchKeyUp,
                    click: this.$onSearchKeyUp
                }
            });

            this.addButton(this.$Search);

            // grid buttons
            this.$Actions = new QUIButton({
                name: 'actions',
                text: QUILocale.get(lg, 'panel.btn.actions'),
                menuCorner: 'topRight',
                position: 'right'
            });

            this.$Actions.appendChild({
                name: 'addPayment',
                text: QUILocale.get(lg, 'panel.btn.paymentBook'),
                icon: 'fa fa-money',
                events: {
                    onClick: this.$onAddPaymentButtonClick
                }
            });

            this.$Actions.appendChild({
                name: 'create',
                text: QUILocale.get(lg, 'panel.btn.createInvoice'),
                icon: 'fa fa-money',
                events: {
                    onClick: this.$clickCreateInvoice
                }
            });

            this.$Actions.appendChild({
                name: 'cancel',
                text: QUILocale.get(lg, 'panel.btn.deleteOrder'),
                icon: 'fa fa-trash',
                events: {
                    onClick: this.$clickDeleteOrder
                }
            });

            this.$Actions.appendChild({
                name: 'copy',
                text: QUILocale.get(lg, 'panel.btn.copyOrder'),
                icon: 'fa fa-copy',
                events: {
                    onClick: this.$clickCopyOrder
                }
            });

            this.$Actions.appendChild({
                name: 'open',
                text: QUILocale.get(lg, 'panel.btn.openOrder'),
                icon: 'fa fa-shopping-cart',
                events: {
                    onClick: this.$clickOpenOrder
                }
            });

            QUI.fireEvent('quiqqerOrderActionButtonCreate', [
                this,
                this.$Actions
            ]);

            this.addButton(this.$Actions);
            this.Loader.show();
        },

        /**
         * event : on resize
         */
        $onResize: function() {
            if (!this.$Grid) {
                return;
            }

            const Body = this.getContent();

            if (!Body) {
                return;
            }

            const size = Body.getSize();

            this.$Grid.setHeight(size.y - 20);
            this.$Grid.setWidth(size.x - 20);
        },

        /**
         * event: on panel inject
         */
        $onInject: function() {
            const self = this;

            this.Loader.show();

            QUIAjax.get([
                'package_quiqqer_currency_ajax_getAllowedCurrencies',
                'package_quiqqer_currency_ajax_getDefault'
            ], function(currencies, currency) {
                let i, len, entry, text;

                if (!currencies.length || currencies.length === 1) {
                    self.$Currency.hide();
                    return;
                }

                self.$Currency.appendChild(
                    new QUIContextMenuItem({
                        name: '',
                        value: '',
                        text: '---'
                    })
                );

                for (i = 0, len = currencies.length; i < len; i++) {
                    entry = currencies[i];

                    text = entry.code + ' ' + entry.sign;
                    text = text.trim();

                    self.$Currency.appendChild(
                        new QUIContextMenuItem({
                            name: entry.code,
                            value: entry.code,
                            text: text
                        })
                    );
                }

                self.$Currency.enable();
                self.$Currency.setAttribute('value', currency.code);
                self.$Currency.setAttribute('text', currency.code);
            }, {
                'package': 'quiqqer/currency'
            });

            this.$Currency.getContextMenu(function(ContextMenu) {
                ContextMenu.setAttribute('showIcons', false);
            });

            Promise.all([
                QUIPackages.isInstalled('quiqqer/shipping'),
                QUIPackages.isInstalled('quiqqer/salesorders')
            ]).then((result) => {
                shippingInstalled = result[0];

                this.$installed['quiqqer/salesorders'] = result[1];

                this.$createGrid();
                this.$onResize();

                this.$Total = new Element('div', {
                    'class': 'order-total'
                }).inject(self.getContent());

                this.refresh().catch(function(err) {
                    console.error(err);
                });
            });
        },

        /**
         * event: on panel destroy
         */
        $onDestroy: function() {
            Orders.removeEvents({
                onOrderCreate: this.$onOrderChange,
                onOrderCopy: this.$onOrderChange,
                onOrderDelete: this.$onOrderChange,
                onOrderSave: this.$onOrderChange
            });
        },

        /**
         * event: on order change, if an order has been changed
         */
        $onOrderChange: function() {
            this.refresh().catch(function(err) {
                console.error(err);
            });
        },

        /**
         * Opens the order panel
         *
         * @param {Number} orderId - ID of the order
         * @return {Promise}
         */
        openOrder: function(orderId) {
            return new Promise(function(resolve) {
                require([
                    'package/quiqqer/order/bin/backend/controls/panels/Order',
                    'utils/Panels'
                ], function(Order, PanelUtils) {
                    const Panel = new Order({
                        orderId: orderId,
                        '#id': orderId
                    });

                    PanelUtils.openPanelInTasks(Panel);
                    resolve(Panel);
                });
            });
        },

        /**
         * event: create click
         */
        $clickCreateOrder: function() {
            const self = this;

            return new Promise(function(resolve) {
                new QUIConfirm({
                    title: QUILocale.get(lg, 'dialog.order.create.title'),
                    text: QUILocale.get(lg, 'dialog.order.create.text'),
                    information: QUILocale.get(lg, 'dialog.order.create.information'),
                    icon: 'fa fa-plus',
                    texticon: 'fa fa-plus',
                    maxHeight: 400,
                    maxWidth: 600,
                    autoclose: false,
                    ok_button: {
                        text: QUILocale.get(lg, 'dialog.order.create.button'),
                        textimage: 'fa fa-plus'
                    },
                    events: {
                        onSubmit: function(Win) {
                            Win.Loader.show();
                            resolve();

                            Orders.createOrder().then(function(orderId) {
                                self.openOrder(orderId);
                                Win.close();
                            }).catch(function() {
                                Win.Loader.hide();
                            });
                        },

                        onCancel: resolve
                    }
                }).open();
            });
        },

        /**
         * event: copy click
         */
        $clickCopyOrder: function() {
            const selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            const orderId = selected[0].hash;

            require([
                'package/quiqqer/erp/bin/backend/controls/dialogs/CopyErpEntityDialog'
            ], (CopyErpEntityDialog) => {
                new CopyErpEntityDialog({
                    hash: orderId,
                    entityPlugin: 'quiqqer/order'
                }).open();
            });
        },

        /**
         * event: delete click
         */
        $clickDeleteOrder: function() {
            const selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            const self = this,
                orderId = selected[0].hash;

            new QUIConfirm({
                title: QUILocale.get(lg, 'dialog.order.delete.title'),
                text: QUILocale.get(lg, 'dialog.order.delete.text'),
                information: QUILocale.get(lg, 'dialog.order.delete.information', {
                    id: selected[0]['prefixed-id']
                }),
                icon: 'fa fa-trash',
                texticon: 'fa fa-trash',
                maxHeight: 400,
                maxWidth: 600,
                autoclose: false,
                ok_button: {
                    text: QUILocale.get('quiqqer/system', 'delete'),
                    textimage: 'fa fa-trash'
                },
                events: {
                    onSubmit: function(Win) {
                        Win.Loader.show();

                        Orders.deleteOrder(orderId).then(function() {
                            self.refresh();
                            Win.close();
                        }).catch(function(err) {
                            Win.Loader.hide();

                            if (typeof err === 'undefined') {
                                return;
                            }

                            if (typeof err.getMessage === 'function') {
                                QUI.getMessageHandler().then(function(MH) {
                                    MH.addError(err.getMessage());
                                });
                            } else {
                                console.error(err);
                            }
                        });
                    }
                }
            }).open();
        },

        /**
         * Open the order
         */
        $clickOpenOrder: function() {
            const selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            this.openOrder(this.$Grid.getSelectedData()[0].hash);
        },

        /**
         * open the create invoice dialog
         */
        $clickCreateInvoice: function() {
            const selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            const orderId = selected[0].hash;

            new QUIConfirm({
                title: QUILocale.get(lg, 'dialog.order.createInvoice.title'),
                text: QUILocale.get(lg, 'dialog.order.createInvoice.text'),
                information: QUILocale.get(lg, 'dialog.order.createInvoice.information', {
                    id: orderId
                }),
                icon: 'fa fa-money',
                texticon: 'fa fa-money',
                maxHeight: 400,
                maxWidth: 600,
                autoclose: false,
                ok_button: {
                    text: QUILocale.get('quiqqer/core', 'create'),
                    textimage: 'fa fa-money'
                },
                events: {
                    onSubmit: function(Win) {
                        Win.Loader.show();

                        Orders.createInvoice(orderId).then(function(newInvoiceId) {
                            Win.close();

                            require([
                                'package/quiqqer/invoice/bin/Invoices',
                                'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoices'
                            ], function(Invoices, Panel) {
                                const HelperPanel = new Panel();

                                Invoices.fireEvent('createInvoice', [
                                    Invoices,
                                    newInvoiceId
                                ]);

                                HelperPanel.openInvoice(newInvoiceId);
                                HelperPanel.destroy();
                            });
                        }).catch(function(err) {
                            Win.Loader.hide();

                            if (typeof err === 'undefined') {
                                return;
                            }

                            QUI.getMessageHandler().then(function(MH) {
                                MH.addError(err.getMessage());
                            });
                        });
                    }
                }
            }).open();
        },

        /**
         * open the create sales order dialog
         */
        $clickCreateSalesOrder: function() {
            const selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            const Row = selected[0];

            new QUIConfirm({
                title: QUILocale.get(lg, 'dialog.order.createSalesOrder.title'),
                text: QUILocale.get(lg, 'dialog.order.createSalesOrder.text'),
                information: QUILocale.get(lg, 'dialog.order.createSalesOrder.information', {
                    id: Row['prefixed-id']
                }),
                icon: 'fa fa-suitcase',
                texticon: 'fa fa-suitcase',
                maxHeight: 400,
                maxWidth: 600,
                autoclose: false,
                ok_button: {
                    text: QUILocale.get('quiqqer/core', 'create'),
                    textimage: 'fa fa-suitcase'
                },
                events: {
                    onSubmit: function(Win) {
                        Win.Loader.show();

                        Orders.createSalesOrder(Row.hash).then(function(salesOrderHash) {
                            Win.close();

                            require([
                                'package/quiqqer/salesorders/bin/js/backend/utils/Panels'
                            ], function(SalesOrderPanelUtils) {
                                SalesOrderPanelUtils.openSalesOrder(salesOrderHash);
                            });
                        }).catch(function(err) {
                            Win.Loader.hide();

                            if (typeof err === 'undefined') {
                                return;
                            }

                            QUI.getMessageHandler().then(function(MH) {
                                MH.addError(err.getMessage());
                            });
                        });
                    }
                }
            }).open();
        },

        /**
         * Open the accordion details of the order
         *
         * @param {Object} data
         */
        $onClickOrderDetails: function(data) {
            if (data.parent.getStyle('display') === 'none') {
                return;
            }

            const row = data.row,
                ParentNode = data.parent;

            ParentNode.setStyle('padding', 10);
            ParentNode.set('html', '<div class="fa fa-spinner fa-spin"></div>');

            Orders.getArticleHtml(this.$Grid.getDataByRow(row).hash).then(function(result) {
                if (result.indexOf('<table') === -1) {
                    ParentNode.set('html', QUILocale.get(lg, 'message.orders.panel.empty.articles'));
                    return;
                }

                ParentNode.set('html', '');

                new Element('div', {
                    'class': 'orders-order-details',
                    html: result
                }).inject(ParentNode);
            });
        },

        /**
         * Toggle the total display
         */
        toggleTotal: function() {
            if (parseInt(this.$Total.getStyle('opacity')) === 1) {
                this.hideTotal();
                return;
            }

            this.showTotal();
        },

        /**
         * Show the total display
         */
        showTotal: function() {
            this.getButtons('total').setActive();
            this.getContent().setStyle('overflow', 'hidden');

            return new Promise(function(resolve) {
                this.$Total.setStyles({
                    display: 'inline-block',
                    opacity: 0
                });

                this.$Grid.setHeight(this.getContent().getSize().y - 130);

                moofx(this.$Total).animate({
                    bottom: 1,
                    opacity: 1
                }, {
                    duration: 200,
                    callback: resolve
                });
            }.bind(this));
        },

        /**
         * Hide the total display
         */
        hideTotal: function() {
            const self = this;

            this.getButtons('total').setNormal();

            return new Promise(function(resolve) {
                self.$Grid.setHeight(self.getContent().getSize().y - 20);

                moofx(self.$Total).animate({
                    bottom: -20,
                    opacity: 0
                }, {
                    duration: 200,
                    callback: function() {
                        self.$Total.setStyles({
                            display: 'none'
                        });

                        resolve();
                    }
                });
            });
        },

        /**
         * Disable the filter
         */
        disableFilter: function() {
            this.$TimeFilter.disable();
            this.$Status.disable();
        },

        /**
         * Enable the filter
         */
        enableFilter: function() {
            this.$TimeFilter.enable();
            this.$Status.enable();
        },

        /**
         * key up event at the search input
         *
         * @param {DOMEvent} event
         */
        $onSearchKeyUp: function(event) {
            if (event.key === 'up' || event.key === 'down' || event.key === 'left' || event.key === 'right') {
                return;
            }

            if (this.$searchDelay) {
                clearTimeout(this.$searchDelay);
            }

            if (event.type === 'click') {
                // workaround, cancel needs time to clear
                (() => {
                    if (this.$currentSearch !== this.$Search.value) {
                        this.$searchDelay = (() => {
                            this.refresh();
                        }).delay(250);
                    }
                }).delay(100);
            }

            if (this.$currentSearch === this.$Search.value) {
                return;
            }

            if (event.key === 'enter') {
                this.$searchDelay = (() => {
                    this.refresh();
                }).delay(250);
            }
        },

        /**
         * event : on payment add button click
         */
        $onAddPaymentButtonClick: function(Button) {
            const selectedData = this.$Grid.getSelectedData();

            if (!selectedData.length) {
                return;
            }

            Button.setAttribute('textimage', 'fa fa-spinner fa-spin');

            const hash = selectedData[0].hash;

            require([
                'package/quiqqer/payment-transactions/bin/backend/controls/IncomingPayments/AddPaymentWindow'
            ], (AddPaymentWindow) => {
                new AddPaymentWindow({
                    entityId: selectedData[0].uuid,
                    entityType: 'Order',
                    paymentId: selectedData[0].paymentId,
                    events: {
                        onSubmitExisting: (txId, Win) => {
                            this.linkTransaction(hash, txId).then(function() {
                                Win.close();
                            }).catch(function() {
                                Win.Loader.hide();
                            });
                        }
                    }
                }).open();
            });
        },

        /**
         * Add a payment to an order
         *
         * @param {String|Number} hash
         * @param {String|Number} amount
         * @param {String} paymentMethod
         * @param {String|Number} date
         *
         * @return {Promise}
         */
        addPayment: function(hash, amount, paymentMethod, date) {
            const self = this;

            this.Loader.show();

            return Orders.addPaymentToOrder(
                hash,
                amount,
                paymentMethod,
                date
            ).then(function() {
                return self.refresh();
            }).then(function() {
                self.Loader.hide();
            }).catch(function(err) {
                console.error(err);
            });
        },

        /**
         * Link transaction to an order
         *
         * @param {String} orderHash
         * @param {String} txId
         * @return {Promise<void>}
         */
        linkTransaction: function(orderHash, txId) {
            this.Loader.show();

            return Orders.linkTransaction(orderHash, txId).then(() => {
                return this.refresh();
            }).then(() => {
                this.Loader.hide();
            }).catch((err) => {
                console.error(err);
            });
        },

        /**
         * Return the grid column
         *
         * @return {({dataIndex: string, dataType: string, showNotInExport: boolean, width: number, header: string}|{dataIndex: string, dataType: string, width: number, header: *}|{dataIndex: string, dataType: string, width: number, header: *})[]}
         */
        $getGridColumnModel: function() {
            let columns = [
                {
                    header: '&nbsp;',
                    dataIndex: 'opener',
                    dataType: 'int',
                    width: 30,
                    showNotInExport: true
                },
                {
                    header: QUILocale.get(lg, 'grid.orderStatus'),
                    dataIndex: 'status',
                    dataType: 'node',
                    width: 100,
                    className: 'grid-align-center clickable'
                },
                {
                    header: QUILocale.get(lg, 'grid.orderNo'),
                    dataIndex: 'prefixed-id',
                    dataType: 'string',
                    width: 80
                }
            ];

            columns = columns.concat([
                {
                    header: QUILocale.get('quiqqer/system', 'name'),
                    dataIndex: 'customer_name',
                    dataType: 'string',
                    width: 200,
                    className: 'clickable',
                    sortable: false
                },
                {
                    header: QUILocale.get(lg, 'grid.customerNo'),
                    dataIndex: 'customer_id_view',
                    dataType: 'string',
                    width: 90,
                    className: 'clickable'
                },
                {
                    header: QUILocale.get(lg, 'grid.orderDate'),
                    dataIndex: 'c_date',
                    dataType: 'date',
                    width: 140
                },
                {
                    header: QUILocale.get(lg, 'grid.sum'),
                    dataIndex: 'display_sum',
                    dataType: 'currency',
                    width: 100,
                    className: 'payment-status-amountCell',
                    sortable: false
                },
                {
                    header: QUILocale.get(lg, 'grid.netto'),
                    dataIndex: 'display_nettosum',
                    dataType: 'currency',
                    width: 100,
                    className: 'payment-status-amountCell',
                    sortable: false
                },
                {
                    header: QUILocale.get(lg, 'grid.vat'),
                    dataIndex: 'display_vatsum',
                    dataType: 'currency',
                    width: 100,
                    className: 'payment-status-amountCell',
                    sortable: false
                },
                {
                    header: QUILocale.get(lg, 'grid.paymentMethod'),
                    dataIndex: 'payment_title',
                    dataType: 'string',
                    width: 180,
                    sortable: false
                },
                {
                    header: QUILocale.get(lg, 'grid.paymentStatus'),
                    dataIndex: 'paid_status_display',
                    dataType: 'string',
                    width: 100,
                    sortable: false,
                    className: 'grid-align-center'
                }
            ]);


            if (shippingInstalled) {
                columns.push({
                    header: QUILocale.get('quiqqer/shipping', 'grid.shippingStatus'),
                    dataIndex: 'shipping_status',
                    dataType: 'node',
                    width: 140,
                    className: 'grid-align-center'
                });
            }

            columns = columns.concat([
                {
                    header: QUILocale.get(lg, 'grid.taxId'),
                    dataIndex: 'taxId',
                    dataType: 'string',
                    width: 120,
                    sortable: false
                },
                {
                    header: QUILocale.get(lg, 'grid.euVatId'),
                    dataIndex: 'euVatId',
                    dataType: 'string',
                    width: 120,
                    sortable: false
                },
                {
                    header: QUILocale.get(lg, 'grid.processingStatus'),
                    dataIndex: 'processing',
                    dataType: 'string',
                    width: 150,
                    sortable: false
                },
                {
                    header: QUILocale.get(lg, 'grid.invoiceNo'),
                    dataIndex: 'invoice_id',
                    dataType: 'integer',
                    width: 100,
                    className: 'clickable'
                },
                {
                    header: QUILocale.get(lg, 'grid.brutto'),
                    dataIndex: 'isbrutto',
                    dataType: 'integer',
                    width: 50
                },
                {
                    header: QUILocale.get(lg, 'grid.hash'),
                    dataIndex: 'uuid',
                    dataType: 'string',
                    width: 280,
                    className: 'monospace'
                },
                {
                    header: QUILocale.get(lg, 'grid.globalProcessId'),
                    dataIndex: 'globalProcessId',
                    dataType: 'string',
                    width: 280,
                    className: 'monospace clickable'
                },
                {
                    header: QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType: 'integer',
                    hidden: true
                },
                {
                    dataIndex: 'paymentId',
                    dataType: 'integer',
                    hidden: true
                },
                {
                    dataIndex: 'customer_id',
                    dataType: 'string',
                    hidden: true
                }
            ]);

            return columns;
        },

        /**
         * Create / render the grid
         */
        $createGrid: function() {
            const self = this;

            // Grid
            const Container = new Element('div').inject(this.getContent());

            // Add additional actions based on isntalled packages
            if (this.$installed['quiqqer/salesorders']) {
                this.$Actions.appendChild({
                    name: 'createSalesOrder',
                    text: QUILocale.get(lg, 'panel.btn.createSalesOrder'),
                    icon: 'fa fa-suitcase',
                    events: {
                        onClick: this.$clickCreateSalesOrder
                    }
                });
            }

            this.$Grid = new Grid(Container, {
                accordion: true,
                serverSort: true,
                autoSectionToggle: false,
                openAccordionOnClick: false,
                toggleiconTitle: '',
                accordionLiveRenderer: this.$onClickOrderDetails,
                pagination: true,
                exportData: true,
                storageKey: 'quiqqer-orders-panel',
                exportTypes: {
                    print: true,
                    pdf: true,
                    csv: true,
                    json: true,
                    xls: true
                },

                columnModel: this.$getGridColumnModel(),
                buttons: [
                    {
                        name: 'create',
                        text: QUILocale.get(lg, 'panel.btn.createOrder'),
                        textimage: 'fa fa-plus',
                        events: {
                            onClick: function(Btn) {
                                Btn.setAttribute('textimage', 'fa fa-spinner fa-spin');

                                self.$clickCreateOrder(Btn).then(function() {
                                    Btn.setAttribute('textimage', 'fa fa-plus');
                                }).catch(function() {
                                    Btn.setAttribute('textimage', 'fa fa-plus');
                                });
                            }
                        }
                    },
                    {
                        name: 'printPdf',
                        text: QUILocale.get(lg, 'order.btn.pdf'),
                        textimage: 'fa fa-print',
                        disabled: true,
                        position: 'right',
                        events: {
                            onClick: this.$onPDFExportButtonClick
                        }
                    },
                    this.$Actions
                ]
            });

            this.$Grid.addEvents({
                onRefresh: this.refresh,
                onClick: this.$refreshButtonStatus,
                onDblClick: function(data) {
                    const Cell = data.cell,
                        position = Cell.getPosition(),
                        rowData = self.$Grid.getDataByRow(data.row);

                    if (Cell.get('data-index') === 'customer_id' ||
                        Cell.get('data-index') === 'customer_id_view' ||
                        Cell.get('data-index') === 'customer_name' ||
                        Cell.get('data-index') === 'invoice_id' ||
                        Cell.get('data-index') === 'status') {

                        require([
                            'qui/controls/contextmenu/Menu',
                            'qui/controls/contextmenu/Item'
                        ], function(QUIMenu, QUIMenuItem) {
                            const Menu = new QUIMenu({
                                events: {
                                    onBlur: function() {
                                        Menu.hide();
                                        Menu.destroy();
                                    }
                                }
                            });

                            Menu.appendChild(
                                new QUIMenuItem({
                                    icon: 'fa fa-calculator',
                                    text: QUILocale.get(lg, 'panel.orders.contextMenu.open.order'),
                                    events: {
                                        onClick: function() {
                                            self.openOrder(rowData.hash);
                                        }
                                    }
                                })
                            );

                            Menu.appendChild(
                                new QUIMenuItem({
                                    icon: 'fa fa-users',
                                    text: QUILocale.get(lg, 'panel.orders.contextMenu.open.user'),
                                    events: {
                                        onClick: function() {
                                            require([
                                                'utils/Panels',
                                                'package/quiqqer/customer/bin/backend/controls/customer/Panel'
                                            ], function(PanelUtils, CustomerPanel) {
                                                PanelUtils.openPanelInTasks(
                                                    new CustomerPanel({
                                                        userId: rowData.customer_id
                                                    })
                                                );
                                            });
                                        }
                                    }
                                })
                            );

                            if (rowData.invoice_id !== '' && rowData.invoice_id !== '---') {
                                Menu.appendChild(
                                    new QUIMenuItem({
                                        icon: 'fa fa-file-text-o',
                                        text: QUILocale.get(lg, 'panel.orders.contextMenu.open.invoice'),
                                        events: {
                                            onClick: function() {
                                                require([
                                                    'utils/Panels',
                                                    'package/quiqqer/invoice/bin/backend/controls/panels/Invoice'
                                                ], function(PanelUtils, InvoicePanel) {
                                                    const Panel = new InvoicePanel({
                                                        invoiceId: rowData.invoice_id
                                                    });

                                                    PanelUtils.openPanelInTasks(Panel);
                                                });
                                            }
                                        }
                                    })
                                );
                            }

                            Menu.appendChild(
                                new QUIMenuItem({
                                    icon: 'fa fa-check',
                                    text: QUILocale.get(lg, 'panel.orders.contextMenu.change.status'),
                                    events: {
                                        onClick: function() {
                                            require([
                                                'package/quiqqer/order/bin/backend/controls/panels/order/StatusWindow'
                                            ], function(StatusWindow) {
                                                new StatusWindow({
                                                    orderId: rowData.hash,
                                                    events: {
                                                        statusChanged: function() {
                                                            self.refresh();
                                                        }
                                                    }
                                                }).open();
                                            });
                                        }
                                    }
                                })
                            );

                            Menu.inject(document.body);
                            Menu.setPosition(position.x, position.y + 30);
                            Menu.setTitle(rowData['prefixed-id']);
                            Menu.show();
                            Menu.focus();
                        });

                        return;
                    }

                    if (typeof data !== 'undefined' && data.cell.get('data-index') === 'globalProcessId') {
                        const rowData = self.$Grid.getDataByRow(data.row);

                        if (rowData.globalProcessId && rowData.globalProcessId !== '') {
                            require([
                                'package/quiqqer/erp/bin/backend/controls/process/ProcessWindow'
                            ], function(ProcessWindow) {
                                new ProcessWindow({
                                    globalProcessId: rowData.globalProcessId
                                }).open();
                            });

                            return;
                        }
                    }


                    const selected = self.$Grid.getSelectedData();

                    if (selected.length) {
                        self.openOrder(selected[0].hash).catch(function(err) {
                            console.error(err);
                        });
                    }
                }
            });
        },

        $onPDFExportButtonClick: function() {
            var selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            return new Promise((resolve) => {
                require([
                    'package/quiqqer/erp/bin/backend/controls/OutputDialog'
                ], (OutputDialog) => {
                    new OutputDialog({
                        entityId: selected[0].hash,
                        entityType: 'Order',
                        comments: false
                    }).open();

                    resolve();
                });
            });
        }
    });
});
