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
    'controls/grid/Grid',
    'package/quiqqer/order/bin/backend/Orders',
    'package/quiqqer/invoice/bin/backend/controls/elements/TimeFilter',
    'Locale',
    'Mustache',

    'text!package/quiqqer/order/bin/backend/controls/panels/Orders.Total.html',
    'text!package/quiqqer/order/bin/backend/controls/panels/Orders.Details.html',
    'css!package/quiqqer/order/bin/backend/controls/panels/Orders.css'

], function (QUI, QUIPanel, QUIButton, QUISelect, QUIConfirm,
             Grid, Orders, TimeFilter, QUILocale,
             Mustache, templateTotal, templateOrderDetails) {
    "use strict";

    var lg = 'quiqqer/order';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/order/bin/backend/controls/panels/Orders',

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
            '$refreshButtonStatus',
            '$onOrderChange',
            '$onClickOrderDetails'
        ],

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                icon : 'fa fa-shopping-cart',
                title: QUILocale.get(lg, 'orders.panel.title')
            });

            this.$Grid       = null;
            this.$Total      = null;
            this.$TimeFilter = null;

            this.addEvents({
                onCreate : this.$onCreate,
                onDestroy: this.$onDestroy,
                onResize : this.$onResize,
                onInject : this.$onInject
            });

            Orders.addEvents({
                onOrderCreate: this.$onOrderChange,
                onOrderDelete: this.$onOrderChange,
                onOrderSave  : this.$onOrderChange,
                onOrderCopy  : this.$onOrderChange
            });
        },

        /**
         * Refresh the grid
         */
        refresh: function () {
            if (!this.$Grid) {
                return;
            }

            this.Loader.show();

            Orders.search({
                perPage: this.$Grid.options.perPage,
                page   : this.$Grid.options.page
            }, {
                from: this.$TimeFilter.getValue().from,
                to  : this.$TimeFilter.getValue().to
            }).then(function (result) {
                var gridData = result.grid;

                gridData.data = gridData.data.map(function (entry) {
                    entry.opener = '&nbsp;';

                    return entry;
                });

                this.$Grid.setData(gridData);
                this.$refreshButtonStatus();

                this.$Total.set(
                    'html',
                    Mustache.render(templateTotal, result.total)
                );

                this.Loader.hide();

            }.bind(this)).catch(function (Err) {
                if ("getMessage" in Err) {
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
        $refreshButtonStatus: function () {
            if (!this.$Grid) {
                return;
            }

            var selected = this.$Grid.getSelectedData(),
                buttons  = this.$Grid.getButtons();

            var Actions = buttons.filter(function (Button) {
                return Button.getAttribute('name') === 'actions';
            })[0];


            if (selected.length) {
                if (selected[0].paid_status === 1 ||
                    selected[0].paid_status === 5) {
                }

                Actions.enable();
                return;
            }

            Actions.disable();
        },

        /**
         * event : on create
         */
        $onCreate: function () {
            var self = this;

            this.getContent().setStyles({
                padding: 10
            });

            this.addButton({
                name     : 'total',
                text     : QUILocale.get(lg, 'panel.btn.total'),
                textimage: 'fa fa-calculator',
                events   : {
                    onClick: this.toggleTotal
                }
            });

            this.$TimeFilter = new TimeFilter({
                name  : 'timeFilter',
                styles: {
                    'float': 'right'
                },
                events: {
                    onChange: this.refresh
                }
            });

            this.addButton(this.$TimeFilter);

            var Actions = new QUIButton({
                name      : 'actions',
                text      : QUILocale.get(lg, 'panel.btn.actions'),
                menuCorner: 'topRight',
                styles    : {
                    'float': 'right'
                }
            });

            Actions.appendChild({
                name  : 'create',
                text  : QUILocale.get(lg, 'panel.btn.createInvoice'),
                icon  : 'fa fa-money',
                events: {
                    onClick: function () {
                    }
                }
            });

            Actions.appendChild({
                name  : 'cancel',
                text  : QUILocale.get(lg, 'panel.btn.deleteOrder'),
                icon  : 'fa fa-times-circle-o',
                events: {
                    onClick: this.$clickDeleteOrder
                }
            });

            Actions.appendChild({
                name  : 'copy',
                text  : QUILocale.get(lg, 'panel.btn.copyOrder'),
                icon  : 'fa fa-copy',
                events: {
                    onClick: this.$clickCopyOrder
                }
            });

            this.addButton(Actions);

            // Grid
            var Container = new Element('div').inject(
                this.getContent()
            );

            this.$Grid = new Grid(Container, {
                accordion            : true,
                autoSectionToggle    : false,
                openAccordionOnClick : false,
                toggleiconTitle      : '',
                accordionLiveRenderer: this.$onClickOrderDetails,
                pagination           : true,
                buttons              : [Actions, {
                    name     : 'create',
                    text     : QUILocale.get(lg, 'panel.btn.createOrder'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: function (Btn) {
                            Btn.setAttribute('textimage', 'fa fa-spinner fa-spin');

                            self.$clickCreateOrder(Btn).then(function () {
                                Btn.setAttribute('textimage', 'fa fa-plus');
                            }).catch(function () {
                                Btn.setAttribute('textimage', 'fa fa-plus');
                            });
                        }
                    }
                }],
                columnModel          : [{
                    header   : '&nbsp;',
                    dataIndex: 'opener',
                    dataType : 'int',
                    width    : 30
                }, {
                    header   : QUILocale.get(lg, 'grid.orderNo'),
                    dataIndex: 'id',
                    dataType : 'integer',
                    width    : 80
                }, {
                    header   : QUILocale.get(lg, 'grid.customerNo'),
                    dataIndex: 'customer_id',
                    dataType : 'integer',
                    width    : 100,
                    className: 'clickable'
                }, {
                    header   : QUILocale.get('quiqqer/system', 'name'),
                    dataIndex: 'customer_name',
                    dataType : 'string',
                    width    : 130,
                    className: 'clickable'
                }, {
                    der      : QUILocale.get(lg, 'grid.orderDate'),
                    dataIndex: 'c_date',
                    dataType : 'date',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'grid.netto'),
                    dataIndex: 'display_nettosum',
                    dataType : 'currency',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'grid.vat'),
                    dataIndex: 'display_vatsum',
                    dataType : 'currency',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'grid.sum'),
                    dataIndex: 'display_sum',
                    dataType : 'currency',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'grid.paymentMethod'),
                    dataIndex: 'payment_title',
                    dataType : 'string',
                    width    : 180
                }, {
                    header   : QUILocale.get(lg, 'grid.taxId'),
                    dataIndex: 'taxId',
                    dataType : 'string',
                    width    : 120
                }, {
                    header   : QUILocale.get(lg, 'grid.euVatId'),
                    dataIndex: 'euVatId',
                    dataType : 'string',
                    width    : 120
                }, {
                    header   : QUILocale.get(lg, 'grid.processingStatus'),
                    dataIndex: 'processing',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'grid.invoiceNo'),
                    dataIndex: 'invoice_id',
                    dataType : 'integer',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'grid.brutto'),
                    dataIndex: 'isbrutto',
                    dataType : 'integer',
                    width    : 50
                }]
            });

            this.$Grid.addEvents({
                onRefresh : this.refresh,
                onClick   : this.$refreshButtonStatus,
                onDblClick: function () {
                    self.openOrder(self.$Grid.getSelectedData()[0].id);
                }
            });

            this.$Total = new Element('div', {
                'class': 'order-total'
            }).inject(this.getContent());
        },

        /**
         * event : on resize
         */
        $onResize: function () {
            if (!this.$Grid) {
                return;
            }

            var Body = this.getContent();

            if (!Body) {
                return;
            }

            var size = Body.getSize();

            this.$Grid.setHeight(size.y - 20);
            this.$Grid.setWidth(size.x - 20);
        },

        /**
         * event: on panel inject
         */
        $onInject: function () {
            this.refresh();
        },

        /**
         * event: on panel destroy
         */
        $onDestroy: function () {
            Orders.removeEvents({
                onOrderCreate: this.$onOrderChange,
                onOrderCopy  : this.$onOrderChange,
                onOrderDelete: this.$onOrderChange,
                onOrderSave  : this.$onOrderChange
            });
        },

        /**
         * event: on order change, if an order has been changed
         */
        $onOrderChange: function () {
            this.refresh();
        },

        /**
         * Opens the order panel
         *
         * @param {Number} orderId - ID of the order
         * @return {Promise}
         */
        openOrder: function (orderId) {
            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/order/bin/backend/controls/panels/Order',
                    'utils/Panels'
                ], function (Order, PanelUtils) {
                    var Panel = new Order({
                        orderId: orderId,
                        '#id'  : orderId
                    });

                    PanelUtils.openPanelInTasks(Panel);
                    resolve(Panel);
                });
            });
        },

        /**
         * event: create click
         *
         * @return {Promise}
         */
        $clickCreateOrder: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                new QUIConfirm({
                    title      : QUILocale.get(lg, 'dialog.order.create.title'),
                    text       : QUILocale.get(lg, 'dialog.order.create.text'),
                    information: QUILocale.get(lg, 'dialog.order.create.information'),
                    icon       : 'fa fa-plus',
                    texticon   : 'fa fa-plus',
                    maxHeight  : 400,
                    maxWidth   : 600,
                    autoclose  : false,
                    ok_button  : {
                        text     : QUILocale.get(lg, 'dialog.order.create.button'),
                        textimage: 'fa fa-plus'
                    },
                    events     : {
                        onSubmit: function (Win) {
                            Win.Loader.show();

                            Orders.createOrder().then(function (orderId) {
                                self.openOrder(orderId).then(resolve);
                                Win.close();
                            }).catch(function () {
                                Win.Loader.hide();
                            });
                        },

                        onCancel: reject
                    }
                }).open();
            });
        },

        /**
         * event: copy click
         */
        $clickCopyOrder: function () {
            var selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            var orderId = selected[0].id;

            return new Promise(function (resolve, reject) {

                new QUIConfirm({
                    title      : QUILocale.get(lg, 'dialog.order.copy.title'),
                    text       : QUILocale.get(lg, 'dialog.order.copy.text'),
                    information: QUILocale.get(lg, 'dialog.order.copy.information', {
                        id: orderId
                    }),
                    icon       : 'fa fa-copy',
                    texticon   : 'fa fa-copy',
                    maxHeight  : 400,
                    maxWidth   : 600,
                    autoclose  : false,
                    ok_button  : {
                        text     : QUILocale.get('quiqqer/system', 'copy'),
                        textimage: 'fa fa-copy'
                    },
                    events     : {
                        onSubmit: function (Win) {
                            Win.Loader.show();

                            Orders.copyOrder(orderId).then(function (newOrderId) {
                                require([
                                    'package/quiqqer/order/bin/backend/controls/panels/Order',
                                    'utils/Panels'
                                ], function (Order, PanelUtils) {
                                    var Panel = new Order({
                                        orderId: newOrderId,
                                        '#id'  : newOrderId
                                    });

                                    PanelUtils.openPanelInTasks(Panel);
                                    Win.close();
                                    resolve();
                                });
                            }).then(function () {
                                Win.Loader.hide();
                            });
                        },

                        onClose: reject
                    }
                }).open();

            });
        },

        /**
         * event: delete click
         */
        $clickDeleteOrder: function () {
            var selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            var orderId = selected[0].id;

            return new Promise(function (resolve, reject) {
                new QUIConfirm({
                    title      : QUILocale.get(lg, 'dialog.order.delete.title'),
                    text       : QUILocale.get(lg, 'dialog.order.delete.text'),
                    information: QUILocale.get(lg, 'dialog.order.delete.information', {
                        id: orderId
                    }),
                    icon       : 'fa fa-trash',
                    texticon   : 'fa fa-trash',
                    maxHeight  : 400,
                    maxWidth   : 600,
                    autoclose  : false,
                    ok_button  : {
                        text     : QUILocale.get('quiqqer/system', 'delete'),
                        textimage: 'fa fa-trash'
                    },
                    events     : {
                        onSubmit: function (Win) {
                            Win.Loader.show();

                            Orders.deleteOrder(orderId).then(function () {
                                Win.close();
                                resolve();
                            }).then(function (err) {
                                Win.Loader.hide();

                                if (typeof err === 'undefined') {
                                    return;
                                }

                                QUI.getMessageHandler().then(function (MH) {
                                    MH.addError(err.getMessage());
                                });
                            });
                        },
                        onClose : reject
                    }
                }).open();
            });
        },

        /**
         * Open the accordion details of the order
         *
         * @param {Object} data
         */
        $onClickOrderDetails: function (data) {
            var row        = data.row,
                ParentNode = data.parent;

            ParentNode.setStyle('padding', 10);
            ParentNode.set('html', '<div class="fa fa-spinner fa-spin"></div>');

            Orders.get(this.$Grid.getDataByRow(row).id).then(function (result) {
                console.warn(result);

                var articles = [];

                if ("articles" in result) {
                    articles = result.articles;
                }

                var list = articles.articles;

                for (var i = 0, len = list.length; i < len; i++) {
                    list[i].position  = i + 1;
                    list[i].articleNo = list[i].articleNo || '---';
                }

                ParentNode.set('html', Mustache.render(templateOrderDetails, {
                    articles       : list,
                    calculations   : articles.calculations,
                    textPosition   : '#',
                    textArticleNo  : QUILocale.get(lg, 'order.products.articleNo'),
                    textDescription: QUILocale.get(lg, 'order.products.description'),
                    textQuantity   : QUILocale.get(lg, 'order.products.quantity'),
                    textUnitPrice  : QUILocale.get(lg, 'order.products.unitPrice'),
                    textTotalPrice : QUILocale.get(lg, 'order.products.price')
                }));
            });
        },

        /**
         * Toggle the total display
         */
        toggleTotal: function () {
            if (parseInt(this.$Total.getStyle('opacity')) === 1) {
                this.hideTotal();
                return;
            }

            this.showTotal();
        },

        /**
         * Show the total display
         */
        showTotal: function () {
            this.getButtons('total').setActive();
            this.getContent().setStyle('overflow', 'hidden');

            return new Promise(function (resolve) {
                this.$Total.setStyles({
                    display: 'inline-block',
                    opacity: 0
                });

                this.$Grid.setHeight(this.getContent().getSize().y - 130);

                moofx(this.$Total).animate({
                    bottom : 1,
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
        hideTotal: function () {
            var self = this;

            this.getButtons('total').setNormal();

            return new Promise(function (resolve) {
                self.$Grid.setHeight(self.getContent().getSize().y - 20);

                moofx(self.$Total).animate({
                    bottom : -20,
                    opacity: 0
                }, {
                    duration: 200,
                    callback: function () {
                        self.$Total.setStyles({
                            display: 'none'
                        });

                        resolve();
                    }
                });
            });
        }
    });
});