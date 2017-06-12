/**
 * @module package/quiqqer/order/bin/backend/controls/panels/Orders
 */
define('package/quiqqer/order/bin/backend/controls/panels/Orders', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Select',
    'controls/grid/Grid',
    'package/quiqqer/order/bin/backend/Orders',
    'Locale'

], function (QUI, QUIPanel, QUISelect, Grid, Orders, QUILocale) {
    "use strict";

    var lg = 'quiqqer/order';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/order/bin/backend/controls/panels/Orders',

        Binds: [
            'refresh',
            '$onCreate',
            '$onResize',
            '$onInject'
        ],

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                icon : 'fa fa-shopping-cart',
                title: QUILocale.get(lg, 'order.panel.title')
            });

            this.$Grid = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onResize: this.$onResize,
                onInject: this.$onInject
            });
        },

        /**
         * Refresh the grid
         */
        refresh: function () {
            this.Loader.show();

            Orders.getList().then(function (result) {
                this.$Grid.setData(result);
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
         * event : on create
         */
        $onCreate: function () {
            this.getContent().setStyles({
                padding: 10
            });

            // Grid
            var Container = new Element('div').inject(
                this.getContent()
            );

            this.$Grid = new Grid(Container, {
                pagination : true,
                buttons    : [],
                columnModel: [{
                    header   : '&nbsp;',
                    dataIndex: 'opener',
                    dataType : 'int',
                    width    : 30
                }, {
                    header   : QUILocale.get(lg, 'grid.type'),
                    dataIndex: 'display_type',
                    dataType : 'node',
                    width    : 30
                }, {
                    header   : QUILocale.get(lg, 'grid.invoiceNo'),
                    dataIndex: 'id',
                    dataType : 'integer',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'grid.orderNo'),
                    dataIndex: 'order_id',
                    dataType : 'integer',
                    width    : 80
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
                onRefresh: this.refresh
            });
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
        }
    });
});