/**
 * @module package/quiqqer/order/bin/backend/controls/panels/Orders
 *
 * @requnre qui/QUI
 * @requnre qui/controls/desktop/Panel
 * @require qui/controls/buttons/Button
 * @requnre qui/controls/buttons/Select
 * @requnre controls/grid/Grid
 * @requnre package/quiqqer/order/bin/backend/Orders
 * @requnre Locale
 * @require Mustache
 */
define('package/quiqqer/order/bin/backend/controls/panels/Orders', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/Select',
    'controls/grid/Grid',
    'package/quiqqer/order/bin/backend/Orders',
    'package/quiqqer/invoice/bin/backend/controls/elements/TimeFilter',
    'Locale',
    'Mustache',

    'text!package/quiqqer/order/bin/backend/controls/panels/Orders.Total.html',
    'css!package/quiqqer/order/bin/backend/controls/panels/Orders.css'

], function (QUI, QUIPanel, QUIButton, QUISelect,
             Grid, Orders, TimeFilter, QUILocale,
             Mustache, templateTotal) {
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
            '$onOrderChange'
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
                orderCreate: this.$onOrderChange,
                orderDelete: this.$onOrderChange,
                orderSave  : this.$onOrderChange
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
                pagination : true,
                buttons    : [Actions, {
                    name     : 'create',
                    text     : QUILocale.get(lg, 'panel.btn.createOrder'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: function (Btn) {
                            Btn.setAttribute('textimage', 'fa fa-spinner fa-spin');

                            self.$clickCreateOrder(Btn).then(function () {
                                Btn.setAttribute('textimage', 'fa fa-plus');
                            });
                        }
                    }
                }],
                columnModel: [{
                    header   : '&nbsp;',
                    dataIndex: 'opener',
                    dataType : 'int',
                    width    : 30
                }, {
                    header   : QUILocale.get(lg, 'grid.orderNo'),
                    dataIndex: 'order_id',
                    dataType : 'integer',
                    width    : 80
                }, {
                    header   : QUILocale.get(lg, 'grid.invoiceNo'),
                    dataIndex: 'id',
                    dataType : 'integer',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'grid.customerNo'),
                    dataIndex: 'customer_id',
                    dataType : 'integer',
                    width    : 100
                }, {
                    header   : QUILocale.get('quiqqer/system', 'name'),
                    dataIndex: 'customer_name',
                    dataType : 'string',
                    width    : 130
                }, {
                    header   : QUILocale.get('quiqqer/system', 'date'),
                    dataIndex: 'date',
                    dataType : 'date',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'grid.status'),
                    dataIndex: 'paid_status_display',
                    dataType : 'string',
                    width    : 120
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
                    header   : QUILocale.get(lg, 'grid.paymentTerm'),
                    dataIndex: 'time_for_payment',
                    dataType : 'date',
                    width    : 120
                }, {
                    header   : QUILocale.get(lg, 'grid.paymentDate'),
                    dataIndex: 'paid_date',
                    dataType : 'date',
                    width    : 120
                }, {
                    header   : QUILocale.get(lg, 'grid.paid'),
                    dataIndex: 'display_paid',
                    dataType : 'currency',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'grid.open'),
                    dataIndex: 'display_toPay',
                    dataType : 'currency',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'grid.brutto'),
                    dataIndex: 'isbrutto',
                    dataType : 'integer',
                    width    : 50
                }, {
                    header   : QUILocale.get(lg, 'grid.taxId'),
                    dataIndex: 'taxId',
                    dataType : 'string',
                    width    : 120
                }, {
                    header   : QUILocale.get(lg, 'grid.orderDate'),
                    dataIndex: 'orderdate',
                    dataType : 'date',
                    width    : 130
                }, {
                    header   : QUILocale.get(lg, 'grid.dunning'),
                    dataIndex: 'dunning_level_display',
                    dataType : 'string',
                    width    : 80
                }, {
                    header   : QUILocale.get(lg, 'grid.processingStatus'),
                    dataIndex: 'processing',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'grid.paymentData'),
                    dataIndex: 'payment_data',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'grid.hash'),
                    dataIndex: 'hash',
                    dataType : 'string',
                    width    : 200
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
                orderCreate: this.$onOrderChange,
                orderDelete: this.$onOrderChange,
                orderSave  : this.$onOrderChange
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
         */
        $clickCreateOrder: function () {
            return Orders.createOrder().then(function (orderId) {
                return this.openOrder(orderId);
            }.bind(this));
        },

        /**
         * event: copy click
         */
        $clickCopyOrder: function () {

        },

        /**
         * event: delete click
         */
        $clickDeleteOrder: function () {

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