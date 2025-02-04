/**
 * @module package/quiqqer/order/bin/backend/controls/settings/Payments
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/order/bin/backend/controls/settings/Payments', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Switch',
    'controls/grid/Grid',
    'package/quiqqer/payments/bin/backend/Payments',
    'Locale',
    'Ajax'

], function (QUI, QUIControl, QUISwitch, Grid, Payments, QUILocale, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/order/bin/backend/controls/settings/Payments',

        Binds: [
            '$onImport',
            'refresh'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Input = null;
            this.$Container = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * Refresh
         *
         * @return Promise
         */
        refresh: function () {
            var self = this,
                current = QUILocale.getCurrent();

            return new Promise(function (resolve) {
                Payments.getPayments().then(function (payments) {
                    return self.$getList().then(function (list) {
                        return [payments, list];
                    });
                }).then(function (result) {
                    var payments = result[0],
                        list = result[1],
                        data = [];

                    var i, len, title, paymentData;

                    var onChange = function () {
                        self.save();
                    };

                    for (i = 0, len = payments.length; i < len; i++) {
                        paymentData = payments[i];
                        title = paymentData.title;

                        if (typeOf(title) === 'object' && current in title) {
                            title = title[current];
                        }

                        data.push({
                            status: new QUISwitch({
                                status: parseInt(list[paymentData.id]),
                                events: {
                                    onChange: onChange
                                }
                            }),
                            id: paymentData.id,
                            title: title,
                            type: paymentData.paymentType.title
                        });
                    }

                    self.$Grid.setData({
                        data: data
                    });

                    resolve();
                });
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this;

            this.$Input = this.getElm();

            this.$Container = new Element('div', {
                styles: {
                    opacity: 0,
                    width: '100%'
                }
            }).wraps(this.$Input);


            var Container = new Element('div', {
                styles: {
                    height: 300,
                    width: '100%',
                    outline: '1px solid red'
                }
            }).inject(this.$Container);


            this.$Grid = new Grid(Container, {
                height: 300,
                width: self.$Container.getSize().x,
                columnModel: [{
                    header: '&nbsp;',
                    dataIndex: 'status',
                    dataType: 'QUI',
                    width: 60
                }, {
                    header: QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType: 'integer',
                    width: 60
                }, {
                    header: QUILocale.get('quiqqer/system', 'title'),
                    dataIndex: 'title',
                    dataType: 'text',
                    width: 140
                }, {
                    header: QUILocale.get('quiqqer/system', 'type'),
                    dataIndex: 'type',
                    dataType: 'text',
                    width: 140
                }],
                onrefresh: this.refresh
            });

            this.refresh().then(function () {
                var header = self.$Grid.container.getElements('.th'),
                    el = header[1];

                el.click(); // workaround
                el.click(); // workaround
                // self.$Grid.sort(1, 'ASC');
            });

            // resizing workaround
            this.$Grid.setWidth(500).then(function () {
                return self.$Grid.setWidth(self.$Container.getSize().x);
            }).then(function () {
                moofx(self.$Container).animate({
                    opacity: 1
                });
            });
        },

        /**
         * Save the current settings
         */
        save: function () {
            var data = this.$Grid.getData();
            var result = {};

            for (var i = 0, len = data.length; i < len; i++) {
                result[data[i].id] = data[i].status.getStatus();
            }

            return this.$executeSave(result);
        },

        /**
         * Return the paymentChangeable
         *
         * @return {Promise|*}
         */
        $getList: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_backend_settings_paymentChangeable_list', resolve, {
                    'package': 'quiqqer/order',
                    onError: reject
                });
            });
        },

        /**
         * Return the paymentChangeable
         *
         * @return {Promise|*}
         */
        $executeSave: function (data) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_backend_settings_paymentChangeable_save', resolve, {
                    'package': 'quiqqer/order',
                    onError: reject,
                    data: JSON.encode(data)
                });
            });
        }
    });
});
