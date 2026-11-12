/**
 * @module package/quiqqer/order/bin/backend/controls/panels/Orders
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
            '$onGridSelectionChange',
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
            this.$totalsVisible = false;
            this.$totalsAnimation = null;
            this.$refreshId = 0;
            this.$destroyed = false;
            this.$TimeFilter = null;
            this.$Search = null;
            this.$Currency = null;
            this.$Actions = null;
            this.$Status = null;
            this.$installed = {};  // list of installed packages related to orders
            this.$multipleSelectionDisabledActions = [];

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
            if (!this.$Grid || this.$destroyed) {
                return Promise.resolve();
            }

            const self = this;
            const refreshId = ++this.$refreshId;

            this.Loader.show();

            if (this.$totalsVisible) {
                this.$Total.setAttribute('aria-busy', 'true');
            }

            // db mapping
            let sortOn = self.$Grid.options.sortOn;

            switch (sortOn) {
                case 'customer_id_view':
                case 'customer_id':
                    sortOn = 'customerId';
                    break;
            }

            this.$currentSearch = this.$Search.value.trim();
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
                sortOn: sortOn,
                includeTotals: this.$totalsVisible
            }, {
                from: from,
                to: to,
                search: this.$currentSearch,
                currency: this.$Currency.getAttribute('value'),
                status: status
            }).then(function(result) {
                if (self.$destroyed || refreshId !== self.$refreshId) {
                    return;
                }

                const gridData = result.grid;

                gridData.data = gridData.data.map(function(entry) {
                    entry.opener = '&nbsp;';

                    entry.status = document.createElement('span');
                    entry.status.className = 'badge badge-pill badge-lg order-status';
                    entry.status.textContent = entry.status_title;

                    if (entry.status_color && CSS.supports('color', entry.status_color)) {
                        entry.status.style.setProperty('--_q-controlConf-color', entry.status_color);
                    }

                    const paymentStatus = document.createElement('span');
                    paymentStatus.className = 'badge badge-pill badge-lg order-status order-payment-status-' +
                        Number.parseInt(entry.paid_status, 10);
                    paymentStatus.textContent = entry.paid_status_display;
                    entry.paid_status_display = paymentStatus;

                    if (shippingInstalled) {
                        entry.shipping_status = document.createElement('span');
                        entry.shipping_status.className = 'badge badge-pill badge-lg order-status order-shipping-status';
                        entry.shipping_status.textContent = entry.shipping_status_title;

                        if (entry.shipping_status_color && CSS.supports('color', entry.shipping_status_color)) {
                            entry.shipping_status.style.setProperty('--_q-controlConf-color', entry.shipping_status_color);
                        }
                    }

                    if (typeof entry.customer_no !== 'undefined') {
                        entry.customer_id_view = entry.customer_no;
                    }

                    return entry;
                });

                self.$Grid.setData(gridData);
                self.$refreshButtonStatus();

                if (self.$totalsVisible && result.total) {
                    self.$renderTotals(result.total, gridData.total);
                }

                self.Loader.hide();
            }).catch(function(Err) {
                if (self.$destroyed || refreshId !== self.$refreshId) {
                    return;
                }

                self.Loader.hide();

                if (self.$totalsVisible) {
                    self.hideTotal();
                }

                if ('getMessage' in Err) {
                    console.error(Err.getMessage());
                    return;
                }

                console.error(Err);
            });
        },

        /**
         * Update selection-dependent buttons after the Grid has completed its
         * click handling.
         */
        $onGridSelectionChange: function() {
            this.$refreshButtonStatus();
            window.setTimeout(this.$refreshButtonStatus, 0);
        },

        /**
         * refresh the button status
         * disabled or enabled
         */
        $refreshButtonStatus: function() {
            if (!this.$Grid) {
                return;
            }

            const selectedCount = this.$Grid.getSelectedIndices().length;
            const Pdf = this.$Grid.getButton('printPdf');
            const actionButtons = this.$Actions.getChildren();

            if (selectedCount === 1) {
                Pdf.enable();
            } else {
                Pdf.disable();
            }

            if (selectedCount <= 1) {
                for (const Button of this.$multipleSelectionDisabledActions) {
                    Button.enable();
                }

                this.$multipleSelectionDisabledActions = [];
            }

            if (!selectedCount) {
                this.$Actions.disable();
                return;
            }

            const multipleOrdersSelected = selectedCount > 1;

            for (const Button of actionButtons) {
                if (!multipleOrdersSelected ||
                    Button.getAttribute('name') === 'cancel' ||
                    (typeof Button.isDisabled === 'function' && Button.isDisabled())) {
                    continue;
                }

                Button.disable();
                this.$multipleSelectionDisabledActions.push(Button);
            }

            this.$Actions.enable();
        },

        /**
         * event : on create
         */
        $onCreate: function() {
            const self = this;

            // panel buttons
            const Body = this.getContent();
            Body.style.padding = '0';

            this.$Total = document.createElement('section');
            this.$Total.className = 'quiqqer-order-totals';
            this.$Total.id = this.getId() + '-totals';
            this.$Total.hidden = true;
            this.$Total.setAttribute('aria-label', QUILocale.get(lg, 'orders.totals.title'));
            this.$Total.setAttribute('aria-live', 'polite');
            Body.appendChild(this.$Total);

            this.addButton({
                name: 'total',
                title: QUILocale.get(lg, 'panel.btn.total'),
                icon: 'fa fa-calculator',
                disabled: true,
                events: {
                    onClick: this.toggleTotal
                }
            });

            const TotalButton = this.getButtons('total').getElm();
            TotalButton.classList.add('quiqqer-order-totals-button');
            TotalButton.tabIndex = 0;
            TotalButton.style.removeProperty('outline');
            TotalButton.firstElementChild?.setAttribute('aria-hidden', 'true');
            TotalButton.setAttribute('aria-label', QUILocale.get(lg, 'panel.btn.total'));
            TotalButton.setAttribute('aria-pressed', 'false');
            TotalButton.setAttribute('aria-controls', this.$Total.id);

            this.$createSearchControl();

            // currency
            this.$Currency = new QUIButton({
                name: 'currency',
                'class': 'quiqqer-order-currency',
                text: '---',
                title: QUILocale.get(lg, 'panel.order.currency.label'),
                disabled: true,
                showIcons: false,
                dropDownIcon: false,
                events: {
                    onChange: function(Menu, Item) {
                        self.$Currency.setAttribute('value', Item.getAttribute('value'));
                        self.$Currency.setAttribute('text', Item.getAttribute('value') || '---');
                        self.refresh();
                    }
                }
            });

            this.addButton(this.$Currency);

            const CurrencyButton = this.$Currency.getElm();
            CurrencyButton.tabIndex = 0;
            CurrencyButton.style.removeProperty('outline');

            const CurrencyArrow = document.createElement('span');
            CurrencyArrow.className = 'quiqqer-order-currency-arrow fa fa-chevron-down';
            CurrencyArrow.setAttribute('aria-hidden', 'true');
            CurrencyButton.appendChild(CurrencyArrow);


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

            // grid buttons
            this.$Actions = new QUIButton({
                name: 'actions',
                'class': 'quiqqer-order-actions-button',
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

            const ActionsButton = this.$Actions.getElm();
            ActionsButton.tabIndex = 0;
            ActionsButton.style.removeProperty('outline');
            ActionsButton.setAttribute('aria-haspopup', 'menu');
            ActionsButton.setAttribute('aria-expanded', 'false');

            this.$Actions.getContextMenu((Menu) => {
                Menu.getElm().classList.add('quiqqer-order-actions-menu');
                Menu.addEvent('show', () => {
                    this.$positionActionsMenu(Menu);
                    ActionsButton.setAttribute('aria-expanded', 'true');
                });
                Menu.addEvent('hide', () => ActionsButton.setAttribute('aria-expanded', 'false'));
            });

            this.Loader.show();
        },

        $positionActionsMenu: function(Menu) {
            const MenuElement = Menu.getElm();
            const button = this.$Actions.getElm().getBoundingClientRect();
            const menu = MenuElement.getBoundingClientRect();
            const left = Math.max(
                8,
                Math.min(button.right - menu.width, window.innerWidth - menu.width - 8)
            );
            const above =
                button.bottom + menu.height + 8 > window.innerHeight &&
                button.top - menu.height - 8 >= 8;
            const top = above ? button.top - menu.height - 8 : button.bottom + 8;

            MenuElement.classList.toggle('qui-context-corner-topRight', !above);
            MenuElement.classList.toggle('qui-context-corner-bottomRight', above);
            Menu.setPosition(
                left + window.scrollX,
                Math.max(8, Math.min(top, window.innerHeight - menu.height - 8)) + window.scrollY
            );
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

            const totalsHeight = this.$totalsVisible ? this.$Total.offsetHeight : 0;

            return Promise.all([
                this.$Grid.setHeight(Math.max(100, size.y - 20 - totalsHeight)),
                this.$Grid.setWidth(size.x - 20)
            ]);
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

                self.$Currency.setAttribute('value', currency.code);
                self.$Currency.setAttribute('text', currency.code);

                if (currencies.length <= 1) {
                    self.$Currency.disable();
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
            }, {
                'package': 'quiqqer/currency'
            });

            this.$Currency.getContextMenu(function(ContextMenu) {
                ContextMenu.setAttribute('showIcons', false);
            });

            Promise.all([
                Orders.isShippingAvailable(),
                QUIPackages.isInstalled('quiqqer/salesorders')
            ]).then((result) => {
                if (this.$destroyed) {
                    return;
                }

                shippingInstalled = result[0];

                this.$installed['quiqqer/salesorders'] = result[1];

                this.$createGrid();
                this.$onResize();
                this.getButtons('total').enable();

                this.refresh().catch(function(err) {
                    console.error(err);
                });
            });
        },

        /**
         * event: on panel destroy
         */
        $onDestroy: function() {
            this.$destroyed = true;
            ++this.$refreshId;
            clearTimeout(this.$searchDelay);
            this.$totalsAnimation?.cancel();

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

            if (selected.length !== 1) {
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

            const multipleOrdersSelected = selected.length > 1;
            const orderIds = selected.map(function(Order) {
                return Order.hash;
            });

            let title = QUILocale.get(lg, 'dialog.order.delete.title');
            let text = QUILocale.get(lg, 'dialog.order.delete.text');
            let information = QUILocale.get(lg, 'dialog.order.delete.information', {
                id: selected[0]['prefixed-id']
            });

            if (multipleOrdersSelected) {
                title = QUILocale.get(lg, 'dialog.orders.delete.title');
                text = QUILocale.get(lg, 'dialog.orders.delete.text');
                information = QUILocale.get(lg, 'dialog.orders.delete.information', {
                    count: selected.length
                });

                const OrdersList = document.createElement('ul');

                for (const Order of selected) {
                    const ListItem = document.createElement('li');
                    const OrderNumber = document.createElement('strong');
                    const details = [];

                    OrderNumber.textContent = String(Order['prefixed-id'] || Order.hash);
                    ListItem.appendChild(OrderNumber);

                    if (Order.customer_name && Order.customer_name !== '---') {
                        details.push(String(Order.customer_name));
                    }

                    if (Order.c_date && Order.c_date !== '---') {
                        details.push(String(Order.c_date));
                    }

                    if (details.length) {
                        ListItem.appendChild(
                            document.createTextNode(' – ' + details.join(' – '))
                        );
                    }

                    OrdersList.appendChild(ListItem);
                }

                information += OrdersList.outerHTML;
            }

            new QUIConfirm({
                title: title,
                text: text,
                information: information,
                icon: 'fa fa-trash',
                texticon: 'fa fa-trash',
                maxHeight: multipleOrdersSelected ? 600 : 400,
                maxWidth: 600,
                autoclose: false,
                ok_button: {
                    text: QUILocale.get('quiqqer/core', 'delete'),
                    textimage: 'fa fa-trash'
                },
                events: {
                    onSubmit: function(Win) {
                        Win.Loader.show();

                        const deletions = orderIds.map(function(orderId) {
                            return Orders.deleteOrder(orderId);
                        });

                        Promise.all(deletions).then(function() {
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

            if (selected.length !== 1) {
                return;
            }

            this.openOrder(selected[0].hash);
        },

        /**
         * open the create invoice dialog
         */
        $clickCreateInvoice: function() {
            const selected = this.$Grid.getSelectedData();

            if (selected.length !== 1) {
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

            if (selected.length !== 1) {
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
            if (this.$totalsVisible) {
                return this.hideTotal();
            }

            return this.showTotal();
        },

        /**
         * Show the total display
         */
        showTotal: function() {
            if (!this.$Grid || this.$destroyed) {
                return Promise.resolve();
            }

            this.$totalsVisible = true;
            this.getButtons('total').setActive();
            this.getButtons('total').getElm().setAttribute('aria-pressed', 'true');

            return this.refresh();
        },

        /**
         * Hide the total display
         */
        hideTotal: function() {
            this.$totalsVisible = false;
            this.$Total.removeAttribute('aria-busy');
            this.getButtons('total').setNormal();
            this.getButtons('total').getElm().setAttribute('aria-pressed', 'false');

            return this.$animateTotals(false);
        },

        $animateTotals: function(visible) {
            const Total = this.$Total;
            const startHeight = Total.hidden ? 0 : Total.getBoundingClientRect().height;
            this.$totalsAnimation?.cancel();
            this.$totalsAnimation = null;

            if (!visible && Total.hidden) {
                Total.classList.remove('quiqqer-order-totals--resizing');
                this.$onResize();
                return Promise.resolve();
            }

            if (typeof Total.animate !== 'function' ||
                window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                Total.hidden = !visible;
                Total.style.overflow = '';
                Total.classList.remove('quiqqer-order-totals--resizing');
                this.$onResize();
                return Promise.resolve();
            }

            if (visible) {
                Total.hidden = false;
            }

            Total.classList.add('quiqqer-order-totals--resizing');
            const endHeight = visible ? Total.offsetHeight : 0;
            const resized = this.$onResize();
            Total.style.overflow = 'hidden';

            const Animation = Total.animate([
                {height: startHeight + 'px'},
                {height: endHeight + 'px'}
            ], {
                duration: 200,
                easing: 'ease-in-out',
                fill: 'forwards'
            });
            this.$totalsAnimation = Animation;

            return Promise.all([Animation.finished, resized]).then(() => {
                if (this.$totalsAnimation !== Animation || this.$destroyed) {
                    return;
                }

                Total.hidden = !visible;
                Animation.cancel();
                Total.style.overflow = '';
                this.$totalsAnimation = null;
                Total.classList.remove('quiqqer-order-totals--resizing');
            }).catch(() => {});
        },

        $renderTotals: function(total, count) {
            const labels = {
                netto: QUILocale.get('quiqqer/erp', 'article.summary.tpl.labelNet'),
                vat: QUILocale.get('quiqqer/erp', 'article.summary.tpl.labelVat'),
                brutto: QUILocale.get('quiqqer/erp', 'article.summary.tpl.labelGross')
            };
            const groupKeys = ['toPay', 'paid', 'total'];
            const groups = groupKeys.map((group) => ({
                type: group,
                title: QUILocale.get(lg, 'orders.totals.' + group),
                metrics: ['netto', 'vat', 'brutto'].map((field) => ({
                    type: field,
                    label: labels[field],
                    value: total['display_' + field + '_' + group],
                    gross: field === 'brutto'
                }))
            }));

            const wasHidden = this.$Total.hidden;

            this.$Total.innerHTML = Mustache.render(templateTotal, {
                title: QUILocale.get(lg, 'orders.totals.title'),
                countLabel: QUILocale.get(lg, 'orders.panel.title'),
                count: count,
                currency: this.$Currency.getAttribute('value') || '',
                groups: groups
            });
            this.$Total.removeAttribute('aria-busy');

            if (wasHidden) {
                this.$animateTotals(true);
            } else {
                this.$onResize();
            }
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
            clearTimeout(this.$searchDelay);

            if (this.$currentSearch === this.$Search.value.trim()) {
                return;
            }

            if (event.key === 'Enter' || event.type === 'search' || !this.$Search.value) {
                this.$searchDelay = setTimeout(() => this.$submitSearch(), 150);
            }
        },

        $submitSearch: function() {
            clearTimeout(this.$searchDelay);

            if (this.$Grid) {
                this.$Grid.options.page = 1;
            }

            return this.refresh();
        },

        $createSearchControl: function() {
            const label = QUILocale.get(lg, 'orders.search.placeholder');
            const SearchGroup = document.createElement('div');
            SearchGroup.className = 'quiqqer-order-search';
            SearchGroup.setAttribute('role', 'search');

            this.$Search = document.createElement('input');
            this.$Search.type = 'search';
            this.$Search.placeholder = label;
            this.$Search.maxLength = 200;
            this.$Search.dataset.name = 'order-search';
            this.$Search.setAttribute('aria-label', label);

            ['keyup', 'search', 'input'].forEach((event) => {
                this.$Search.addEventListener(event, this.$onSearchKeyUp);
            });

            SearchGroup.appendChild(this.$Search);

            const SearchButton = new QUIButton({
                name: 'search',
                icon: 'fa fa-search',
                events: {
                    onClick: () => this.$submitSearch()
                }
            });
            this.addButton(SearchButton);

            const ButtonElm = SearchButton.getElm();
            ButtonElm.tabIndex = 0;
            ButtonElm.style.removeProperty('outline');
            ButtonElm.firstElementChild?.setAttribute('aria-hidden', 'true');
            ButtonElm.setAttribute('aria-label', label);
            ButtonElm.before(SearchGroup);
            SearchGroup.appendChild(ButtonElm);
        },

        /**
         * event : on payment add button click
         */
        $onAddPaymentButtonClick: function(Button) {
            const selectedData = this.$Grid.getSelectedData();

            if (selectedData.length !== 1) {
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
                    styles: {
                        'text-align': 'center',
                        'justify-content': 'center'
                    },
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
                    header: QUILocale.get('quiqqer/core', 'name'),
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
                    styles: {
                        'text-align': 'center',
                        'justify-content': 'center'
                    },
                    dataType: 'node',
                    width: 100,
                    sortable: false,
                    className: 'grid-align-center'
                }
            ]);


            if (shippingInstalled) {
                columns.push({
                    header: QUILocale.get('quiqqer/shipping', 'grid.shippingStatus'),
                    dataIndex: 'shipping_status',
                    styles: {
                        'text-align': 'center',
                        'justify-content': 'center'
                    },
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
                    header: QUILocale.get('quiqqer/core', 'id'),
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
            const GridContainer = document.createElement('div');
            GridContainer.className = 'quiqqer-order-grid-container';
            this.getContent().appendChild(GridContainer);

            const Container = document.createElement('div');
            GridContainer.appendChild(Container);

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
                multipleSelection: true,
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
                onClick: this.$onGridSelectionChange,
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
            const selected = this.$Grid.getSelectedData();
            const selectedCount = this.$Grid.getSelectedIndices().length;

            if (selectedCount !== 1 || selected.length !== 1) {
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
