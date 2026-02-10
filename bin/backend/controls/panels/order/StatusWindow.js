/**
 * @module package/quiqqer/order/bin/backend/controls/panels/order/StatusWindow
 */
define('package/quiqqer/order/bin/backend/controls/panels/order/StatusWindow', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'package/quiqqer/order/bin/backend/ProcessingStatus',
    'package/quiqqer/erp/bin/backend/utils/ERPEntities',
    'package/quiqqer/order/bin/backend/Orders',
    'Locale',
    'Ajax'

], function(QUI, QUIConfirm, ProcessingStatus, ERPEntities, Orders, QUILocale, QUIAjax) {
    'use strict';

    const lg = 'quiqqer/order';

    return new Class({

        Extends: QUIConfirm,
        Type: 'package/quiqqer/order/bin/backend/controls/panels/order/StatusWindow',

        Binds: [
            '$onOpen',
            '$onSubmit'
        ],

        options: {
            orderId: false,
            maxWidth: 550,
            maxHeight: 300,
            autoclose: false
        },

        initialize: function(options) {
            this.parent(options);

            this.setAttributes({
                icon: 'fa fa-check',
                title: QUILocale.get(lg, 'window.status.title', {
                    orderId: this.getAttribute('orderId')
                })
            });

            this.addEvents({
                onOpen: this.$onOpen,
                onSubmit: this.$onSubmit
            });
        },

        /**
         * event: on import
         */
        $onOpen: function() {
            this.Loader.show();
            this.getContent().set('html', '');

            let Select;

            return ERPEntities.getEntity(this.getAttribute('orderId'), 'quiqqer/order').then((data) => {
                this.setAttributes({
                    icon: 'fa fa-check',
                    title: QUILocale.get(lg, 'window.status.title', {
                        orderId: data.prefixedNumber
                    })
                });

                this.refresh();

                new Element('p', {
                    html: QUILocale.get(lg, 'window.status.text', {
                        orderId: data.prefixedNumber
                    })
                }).inject(this.getContent());

                Select = new Element('select', {
                    styles: {
                        display: 'block',
                        margin: '20px auto 0',
                        width: '80%'
                    }
                }).inject(this.getContent());

            }).then(() => {
                return ProcessingStatus.getList();
            }).then((statusList) => {
                statusList = statusList.data;

                new Element('option', {
                    html: '',
                    value: '',
                    'data-color': ''
                }).inject(Select);

                for (let i = 0, len = statusList.length; i < len; i++) {
                    new Element('option', {
                        html: statusList[i].title,
                        value: statusList[i].id,
                        'data-color': statusList[i].color
                    }).inject(Select);
                }
            }).then(() => {
                return Orders.get(this.getAttribute('orderId'));
            }).then((orderData) => {
                Select.value = orderData.status;

                this.Loader.hide();
            });
        },

        /**
         * event: on submit
         */
        $onSubmit: function() {
            this.Loader.show();

            QUIAjax.post('package_quiqqer_order_ajax_backend_setStatus', () => {
                this.fireEvent('statusChanged', [this]);
                this.close();
            }, {
                'package': 'quiqqer/order',
                orderId: this.getAttribute('orderId'),
                status: this.getContent().getElement('select').value,
                onError: () => {
                    this.Loader.hide();
                }
            });
        }
    });
});