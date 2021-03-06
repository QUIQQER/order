/**
 * @module package/quiqqer/order/bin/backend/controls/panels/Orders
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/order/bin/backend/controls/panels/Order', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/ButtonMultiple',
    'qui/controls/buttons/Separator',
    'qui/controls/windows/Confirm',
    'package/quiqqer/order/bin/backend/Orders',
    'package/quiqqer/order/bin/backend/ProcessingStatus',
    'package/quiqqer/payments/bin/backend/Payments',
    'package/quiqqer/erp/bin/backend/controls/Comments',
    'package/quiqqer/erp/bin/backend/controls/articles/Text',
    'utils/Lock',
    'Ajax',
    'Locale',
    'Mustache',
    'Users',
    'Packages',

    'text!package/quiqqer/order/bin/backend/controls/panels/Order.Data.html',
    'text!package/quiqqer/order/bin/backend/controls/panels/Order.ChangeDate.html',
    'css!package/quiqqer/order/bin/backend/controls/panels/Order.css'

], function (QUI, QUIPanel, QUIButton, QUIButtonMultiple, QUISeparator, QUIConfirm,
             Orders, ProcessingStatus, Payments, Comments, TextArticle, Locker,
             QUIAjax, QUILocale, Mustache, Users, Packages,
             templateData, templateChangeDate) {
    "use strict";

    var lg                = 'quiqqer/order';
    var shippingInstalled = false;

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/order/bin/backend/controls/panels/Order',

        Binds: [
            'update',
            'save',
            'refresh',
            'openInfo',
            'openHistory',
            'openCommunication',
            'openArticles',
            'openDeleteDialog',
            'openPayments',
            'openCopyDialog',
            'openPostDialog',
            'toggleSort',
            '$onCreate',
            '$onDestroy',
            '$onInject',
            '$onOrderDelete',
            '$showLockMessage',
            '$editDate'
        ],

        options: {
            orderId        : false,
            customerId     : false,
            customer       : {},
            addressInvoice : {},
            addressDelivery: {},
            data           : {},
            articles       : [],

            paymentId     : '',
            paymentMethod : '',
            paymentData   : '',
            paymentTime   : '',
            paymentAddress: ''
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                icon : 'fa fa-shopping-cart',
                title: QUILocale.get(lg, 'order.panel.title', {
                    orderId: this.getAttribute('orderId')
                })
            });

            this.$locked = false;

            this.$Customer        = null;
            this.$AddressInvoice  = null;
            this.$AddressDelivery = null;

            this.$ArticleList        = null;
            this.$ArticleListSummary = null;

            this.$Actions       = null;
            this.$AddProduct    = null;
            this.$ArticleSort   = null;
            this.$AddSeparator  = null;
            this.$SortSeparator = null;

            this.$priceFactors          = null;
            this.$serializedList        = {};
            this.$initialStatus         = false;
            this.$initialShippingStatus = false;

            this.addEvents({
                onCreate : this.$onCreate,
                onDestroy: this.$onDestroy,
                onInject : this.$onInject
            });

            Orders.addEvents({
                onOrderDelete: this.$onOrderDelete
            });
        },

        /**
         * Return the lock key
         *
         * @return {string}
         */
        $getLockKey: function () {
            return 'order-edit-' + this.getAttribute('orderId');
        },

        /**
         * Return the lock group
         * @return {string}
         */
        $getLockGroups: function () {
            return 'quiqqer/order';
        },

        /**
         * Refresh the grid
         */
        refresh: function () {
            var self    = this,
                orderId = this.getAttribute('orderId');

            return new Promise(function (resolve, reject) {
                Orders.get(orderId).then(function (data) {
                    self.setAttribute('customerId', data.customerId);
                    self.setAttribute('customer', data.customer);
                    self.setAttribute('data', data.data);
                    self.setAttribute('hash', data.hash);
                    self.setAttribute('addressInvoice', data.addressInvoice);
                    self.setAttribute('addressDelivery', data.addressDelivery);
                    self.setAttribute('currency', data.currency.code);

                    if (data.addressDelivery &&
                        (typeof data.addressDelivery.length === 'undefined' || data.addressDelivery.length) &&
                        JSON.stringify(data.addressDelivery) !== JSON.stringify(data.addressInvoice)) {
                        self.setAttribute('hasDeliveryAddress', true);
                    }

                    self.setAttribute('cDate', data.cDate);
                    self.setAttribute('cUser', data.cUser);
                    self.setAttribute('cUsername', data.cUsername);

                    self.setAttribute('paymentId', data.paymentId);
                    self.setAttribute('paymentMethod', data.paymentMethod);
                    self.setAttribute('status', data.status);
                    self.setAttribute('shippingStatus', data.shippingStatus);

                    self.$initialStatus         = parseInt(data.status);
                    self.$initialShippingStatus = parseInt(data.shippingStatus);

                    if (data.articles) {
                        self.$serializedList = data.articles;

                        if (typeof self.$serializedList.articles !== 'undefined') {
                            self.setAttribute('articles', self.$serializedList.articles);
                        }
                    }

                    resolve();
                }, reject);
            });
        },

        /**
         * Update the order, save all data
         *
         * @return {Promise}
         */
        update: function () {
            if (this.$locked) {
                return Promise.reject('Order is locked');
            }

            var self    = this,
                orderId = this.getAttribute('orderId');

            this.Loader.show();
            this.$unLoadCategory();

            var data = {
                customerId     : this.getAttribute('customerId'),
                customer       : this.getAttribute('customer'),
                currency       : this.getAttribute('currency'),
                addressInvoice : this.getAttribute('addressInvoice'),
                addressDelivery: this.getAttribute('addressDelivery'),
                data           : this.getAttribute('data'),
                articles       : this.getAttribute('articles'),
                paymentId      : this.getAttribute('paymentId'),
                status         : this.getAttribute('status'),
                shippingStatus : this.getAttribute('shippingStatus'),
                cDate          : this.getAttribute('cDate'),
                priceFactors   : this.$serializedList.priceFactors
            };

            return new Promise(function (resolve) {
                self.$dialogStatusChangeNotification(data.status).then(function (notificationText) {
                    data.notification = notificationText;

                    return self.$dialogShippingStatusChangeNotification(data.shippingStatus);
                }).then(function (notificationShippingText) {
                    data.notificationShipping = notificationShippingText;

                    Orders.updateOrder(orderId, data).then(function () {
                        resolve();
                        self.Loader.hide();
                    });
                });
            });
        },

        /**
         * Alias for update
         *
         * @return {Promise}
         */
        save: function () {
            return this.update();
        },

        /**
         * event : on create
         */
        $onCreate: function () {
            var self = this;

            self.Loader.show();

            this.$AddProduct = new QUIButtonMultiple({
                textimage: 'fa fa-plus',
                text     : QUILocale.get(lg, 'panel.order.button.buttonAdd'),
                events   : {
                    onClick: function () {
                        if (self.$ArticleList) {
                            self.openProductSearch();
                        }
                    }
                }
            });

            this.$AddProduct.hide();

            this.$AddProduct.appendChild({
                text  : QUILocale.get(lg, 'panel.order.article.buttonAdd.custom'),
                events: {
                    onClick: function () {
                        if (self.$ArticleList) {
                            self.$ArticleList.insertNewProduct();
                        }
                    }
                }
            });

            this.$AddProduct.appendChild({
                text  : QUILocale.get(lg, 'panel.order.article.buttonAdd.text'),
                events: {
                    onClick: function () {
                        if (self.$ArticleList) {
                            self.$ArticleList.addArticle(new TextArticle());
                        }
                    }
                }
            });

            this.$AddSeparator  = new QUISeparator();
            this.$SortSeparator = new QUISeparator();

            this.$ArticleSort = new QUIButton({
                name     : 'sort',
                textimage: 'fa fa-sort',
                text     : QUILocale.get(lg, 'panel.order.button.article.sort.text'),
                events   : {
                    onClick: this.toggleSort
                }
            });

            this.$ArticleSort.hide();

            // insert buttons
            this.addButton({
                name     : 'save',
                textimage: 'fa fa-save',
                text     : QUILocale.get('quiqqer/quiqqer', 'save'),
                events   : {
                    onClick: this.update
                }
            });

            this.addButton(this.$AddSeparator);
            this.addButton(this.$AddProduct);
            this.addButton(this.$SortSeparator);
            this.addButton(this.$ArticleSort);


            this.addButton({
                name  : 'lock',
                icon  : 'fa fa-warning',
                styles: {
                    background: '#fcf3cf',
                    color     : '#7d6608',
                    'float'   : 'right'
                },
                events: {
                    onClick: this.$showLockMessage
                }
            });

            this.getButtons('lock').hide();


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
                    onClick: this.openPostDialog
                }
            });

            Actions.appendChild({
                name  : 'copy',
                text  : QUILocale.get(lg, 'panel.btn.copyOrder'),
                icon  : 'fa fa-copy',
                events: {
                    onClick: this.openCopyDialog
                }
            });

            Actions.appendChild({
                name  : 'copy',
                text  : QUILocale.get(lg, 'panel.btn.deleteOrder'),
                icon  : 'fa fa-trash',
                events: {
                    onClick: this.openDeleteDialog
                }
            });


            this.addButton(Actions);


            // categories
            this.addCategory({
                icon  : 'fa fa-info',
                name  : 'info',
                title : QUILocale.get(lg, 'panel.order.category.data'),
                text  : QUILocale.get(lg, 'panel.order.category.data'),
                events: {
                    onClick: this.openInfo
                }
            });

            this.addCategory({
                icon  : 'fa fa-shopping-basket',
                name  : 'articles',
                title : QUILocale.get(lg, 'panel.order.category.articles'),
                text  : QUILocale.get(lg, 'panel.order.category.articles'),
                events: {
                    onClick: this.openArticles
                }
            });

            this.addCategory({
                icon  : 'fa fa-money',
                name  : 'payments',
                title : QUILocale.get(lg, 'panel.order.category.payment'),
                text  : QUILocale.get(lg, 'panel.order.category.payment'),
                events: {
                    onClick: this.openPayments
                }
            });

            this.addCategory({
                icon  : 'fa fa-comments-o',
                name  : 'communication',
                title : QUILocale.get(lg, 'panel.order.category.details'),
                text  : QUILocale.get(lg, 'panel.order.category.details'),
                events: {
                    onClick: this.openCommunication
                }
            });

            this.addCategory({
                icon  : 'fa fa-history',
                name  : 'history',
                title : QUILocale.get(lg, 'panel.order.category.history'),
                text  : QUILocale.get(lg, 'panel.order.category.history'),
                events: {
                    onClick: this.openHistory
                }
            });
        },

        /**
         * event: on panel destroy
         */
        $onDestroy: function () {
            Orders.removeEvents({
                onOrderDelete: this.$onOrderDelete
            });

            Locker.unlock(
                this.$getLockKey(),
                this.$getLockGroups()
            );
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;

            this.Loader.show();

            Locker.isLocked(
                this.$getLockKey(),
                this.$getLockGroups()
            ).then(function (isLocked) {
                if (isLocked) {
                    self.$locked = isLocked;
                    self.lockPanel();
                    return;
                }

                return Locker.lock(
                    self.$getLockKey(),
                    self.$getLockGroups()
                );
            }).then(function () {
                return Packages.isInstalled('quiqqer/shipping');
            }).then(function (isInstalled) {
                shippingInstalled = isInstalled;

                return self.refresh();
            }).then(this.openInfo).catch(function (Err) {
                QUI.getMessageHandler().then(function (MH) {
                    if ("getMessage" in Err) {
                        MH.addError(Err.getMessage());
                    } else {
                        console.error(Err);
                    }
                });
            });
        },

        /**
         * lock the complete panel
         */
        lockPanel: function () {
            this.getButtons('save').disable();
            this.getButtons('actions').disable();
            this.getButtons('lock').show();

            var categories = this.getCategoryBar().getChildren();

            for (var i = 0, len = categories.length; i < len; i++) {
                if (categories[i].getAttribute('name') === 'info') {
                    continue;
                }

                categories[i].disable();
            }
        },

        /**
         * Unlock the locked order and refresh the panel
         *
         * @return {*|Promise|void}
         */
        unlockPanel: function () {
            var self = this;

            this.Loader.show();

            return Locker.unlock(
                this.$getLockKey(),
                this.$getLockGroups()
            ).then(function () {
                return Locker.isLocked(
                    self.$getLockKey(),
                    self.$getLockGroups()
                );
            }).then(function (isLocked) {
                if (isLocked) {
                    return;
                }

                self.$locked = isLocked;

                self.getButtons('save').enable();
                self.getButtons('actions').enable();
                self.getButtons('lock').hide();

                self.getButtons('lock').setAttribute(
                    'title',
                    QUILocale.get(lg, 'message.invoice.is.locked', isLocked)
                );

                var categories = self.getCategoryBar().getChildren();

                for (var i = 0, len = categories.length; i < len; i++) {
                    categories[i].enable();
                }

                return self.refresh();
            }).then(function () {
                return self.openInfo();
            });
        },

        /**
         * show the lock message window
         */
        $showLockMessage: function () {
            var self    = this,
                btnText = QUILocale.get('quiqqer/quiqqer', 'submit');

            if (window.USER.isSU) {
                btnText = QUILocale.get(lg, 'button.unlock.order.is.locked');
            }

            new QUIConfirm({
                title      : QUILocale.get(lg, 'window.unlock.order.title'),
                icon       : 'fa fa-warning',
                texticon   : 'fa fa-warning',
                text       : QUILocale.get(lg, 'window.unlock.order.text', this.$locked),
                information: QUILocale.get(lg, 'message.order.is.locked', this.$locked),
                autoclose  : false,
                maxHeight  : 400,
                maxWidth   : 600,
                ok_button  : {
                    text: btnText
                },

                events: {
                    onSubmit: function (Win) {
                        if (!window.USER.isSU) {
                            Win.close();
                            return;
                        }

                        Win.Loader.show();

                        self.unlockPanel().then(function () {
                            Win.close();
                        });
                    }
                }
            }).open();
        },

        //region categories

        /**
         * Open the information category
         */
        openInfo: function () {
            var self = this;

            this.Loader.show();
            this.getCategory('info').setActive();

            return this.$closeCategory().then(function (Container) {
                Container.set({
                    html: Mustache.render(templateData, {
                        textOrderCustomer       : QUILocale.get(lg, 'customerTitle'),
                        textOrderInvoiceAddress : QUILocale.get(lg, 'invoiceAddress'),
                        textOrderDeliveryAddress: QUILocale.get(lg, 'deliveryAddress'),
                        textEUVAT               : QUILocale.get('quiqqer/erp', 'user.settings.euVatId'),
                        textTAXNo               : QUILocale.get('quiqqer/erp', 'user.settings.taxId'),
                        textAddresses           : QUILocale.get(lg, 'address'),
                        textCustomer            : QUILocale.get(lg, 'customer'),
                        textCompany             : QUILocale.get('quiqqer/system', 'company'),
                        textStreet              : QUILocale.get('quiqqer/system', 'street'),
                        textZip                 : QUILocale.get('quiqqer/system', 'zip'),
                        textCity                : QUILocale.get('quiqqer/system', 'city'),
                        textCountry             : QUILocale.get('quiqqer/system', 'country'),
                        textOrderData           : QUILocale.get(lg, 'panel.order.data.title'),
                        textOrderDate           : QUILocale.get(lg, 'panel.order.data.date'),
                        textOrderedBy           : QUILocale.get(lg, 'panel.order.data.orderedBy'),
                        textStatus              : QUILocale.get(lg, 'panel.order.data.status'),
                        textPaymentTitle        : QUILocale.get(lg, 'order.payment.panel.paymentTitle'),
                        textPayment             : QUILocale.get(lg, 'order.payment.panel.payment'),
                        textShippingStatus      : QUILocale.get(lg, 'panel.order.shipping.data.status'),
                        textShippingStatusTitle : QUILocale.get(lg, 'panel.order.shipping.data.title'),

                        textCurrencyTitle: QUILocale.get(lg, 'panel.order.currency.title'),
                        textCurrency     : QUILocale.get(lg, 'panel.order.currency.label'),

                        messageDifferentDeliveryAddress: QUILocale.get(lg, 'message.different,delivery.address')
                    })
                });

                return QUI.parse(Container);
            }).then(function () {
                var Content        = self.getContent(),
                    deliverAddress = Content.getElement('[name="differentDeliveryAddress"]');

                var TaxId          = Content.getElement('[name="quiqqer.erp.taxId"]');
                var EUVAT          = Content.getElement('[name="quiqqer.erp.euVatId"]');
                var DateField      = Content.getElement('[name="date"]');
                var DateEdit       = Content.getElement('[name="edit-date"]');
                var OrderedByField = Content.getElement('[name="orderedBy"]');

                var customer = self.getAttribute('customer');

                var Currency   = Content.getElement('[name="currency"]');
                Currency.value = self.getAttribute('currency');

                if (customer) {
                    TaxId.value = '';
                    EUVAT.value = '';

                    if ("quiqqer.erp.taxId" in customer && customer["quiqqer.erp.taxId"]) {
                        TaxId.value = customer['quiqqer.erp.taxId'];
                    }

                    if ("quiqqer.erp.euVatId" in customer && customer["quiqqer.erp.euVatId"]) {
                        EUVAT.value = customer['quiqqer.erp.euVatId'];
                    }
                }

                self.$Customer = QUI.Controls.getById(
                    Content.getElement('input[name="customer"]').get('data-quiid')
                );

                self.$AddressDelivery = QUI.Controls.getById(
                    Content.getElement('.order-delivery').get('data-quiid')
                );

                self.$AddressInvoice = QUI.Controls.getById(
                    Content.getElement('.order-invoice').get('data-quiid')
                );

                // events
                self.$Customer.addEvent('change', function (Select) {
                    var userId = parseInt(Select.getValue());

                    self.$AddressInvoice.setAttribute('userId', userId);
                    self.$AddressDelivery.setAttribute('userId', userId);
                    self.setAttribute('customerId', userId);
                });

                deliverAddress.addEvent('change', function (event) {
                    var Table     = deliverAddress.getParent('table'),
                        closables = Table.getElements('.closable');

                    var data = self.$AddressInvoice.getValue();

                    if (!data.uid) {
                        if (event) {
                            event.stop();
                        }

                        var Customer = QUI.Controls.getById(
                            Content.getElement('[name="customer"]').get('data-quiid')
                        );

                        this.checked = false;

                        QUI.getMessageHandler().then(function (MH) {
                            MH.addInformation(
                                QUILocale.get(lg, 'message.select.customer'),
                                Customer.getElm()
                            );
                        });

                        return;
                    }

                    if (this.checked) {
                        closables.setStyle('display', null);
                        return;
                    }

                    closables.setStyle('display', 'none');
                });


                // values
                if (self.getAttribute('customerId') !== false) {
                    self.$Customer.addItem(self.getAttribute('customerId'));

                    var User = Users.get(self.getAttribute('customerId'));

                    var userLoaded = function () {
                        if (User.isLoaded()) {
                            return Promise.resolve();
                        }
                        return User.load();
                    };

                    userLoaded().then(function () {
                        if (EUVAT.value === '' && User.getAttribute('quiqqer.erp.euVatId')) {
                            EUVAT.value = User.getAttribute('quiqqer.erp.euVatId');
                        }

                        if (TaxId.value === '' && User.getAttribute('quiqqer.erp.taxId')) {
                            TaxId.value = User.getAttribute('quiqqer.erp.taxId');
                        }
                    });
                }

                if (self.getAttribute('addressInvoice')) {
                    self.$AddressInvoice.setValue(self.getAttribute('addressInvoice'));
                }

                if (self.getAttribute('addressDelivery') && self.getAttribute('hasDeliveryAddress')) {
                    self.$AddressDelivery.setValue(self.getAttribute('addressDelivery'));

                    deliverAddress.checked = true;

                    deliverAddress.getParent('table')
                                  .getElements('.closable')
                                  .setStyle('display', null);
                }

                if (self.getAttribute('cDate')) {
                    DateField.value = self.getAttribute('cDate');
                }

                if (self.getAttribute('cUsername') && self.getAttribute('cUser')) {
                    OrderedByField.value = self.getAttribute('cUsername') + ' (' + self.getAttribute('cUser') + ')';
                }

                DateEdit.addEvent('click', self.$editDate);

                EUVAT.disabled = false;
                TaxId.disabled = false;

                if (!shippingInstalled) {
                    return;
                }

                return self.$initShippingStatus();
            }).then(function () {
                // payments
                var Select  = self.getContent().getElement('[name="paymentId"]'),
                    current = QUILocale.getCurrent();

                return Payments.getPayments().then(function (payments) {
                    new Element('option', {
                        html : '',
                        value: ''
                    }).inject(Select);

                    var i, len, title;

                    for (i = 0, len = payments.length; i < len; i++) {
                        title = payments[i].title;

                        if (typeOf(title) === 'object' && typeof title[current] !== 'undefined') {
                            title = title[current];
                        }

                        new Element('option', {
                            html : title,
                            value: payments[i].id
                        }).inject(Select);
                    }

                    Select.disabled = false;
                    Select.value    = self.getAttribute('paymentId');
                });
            }).then(function () {
                // order status
                var StatusSelect = self.getContent().getElement('.order-data-status-field-select');
                var StatusColor  = self.getContent().getElement('.order-data-status-field-colorPreview');

                StatusSelect.addEvent('change', function () {
                    var Option = StatusSelect.getElement('[value="' + this.value + '"]');

                    if (Option) {
                        StatusColor.setStyle('backgroundColor', Option.get('data-color'));
                    } else {
                        StatusColor.setStyle('backgroundColor', '');
                    }
                });

                return ProcessingStatus.getList().then(function (statusList) {
                    statusList = statusList.data;

                    new Element('option', {
                        html        : '',
                        value       : '',
                        'data-color': ''
                    }).inject(StatusSelect);

                    for (var i = 0, len = statusList.length; i < len; i++) {
                        new Element('option', {
                            html        : statusList[i].title,
                            value       : statusList[i].id,
                            'data-color': statusList[i].color
                        }).inject(StatusSelect);
                    }

                    StatusSelect.disabled = false;
                    StatusSelect.value    = self.getAttribute('status');
                    StatusSelect.fireEvent('change');

                    if (self.$initialStatus === false) {
                        self.$initialStatus = parseInt(self.getAttribute('status'));
                    }
                });
            }).then(function () {
                return self.$openCategory();
            }).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * Open payments list
         */
        openPayments: function () {
            var self = this;

            this.Loader.show();
            this.getCategory('payments').setActive();

            return this.$closeCategory().then(function (Container) {
                return new Promise(function (resolve) {
                    require([
                        'package/quiqqer/payment-transactions/bin/backend/controls/IncomingPayments/TransactionList'
                    ], function (TransactionList) {
                        new TransactionList({
                            Panel     : self,
                            hash      : self.getAttribute('hash'),
                            entityType: 'Order',
                            disabled  : self.$locked,
                            events    : {
                                onLoad          : resolve,
                                onAddTransaction: function (data, Control) {
                                    Orders.addPaymentToOrder(
                                        self.getAttribute('hash'),
                                        data.amount,
                                        data.payment_method,
                                        data.date
                                    ).then(function () {
                                        Control.refresh();
                                    });
                                }
                            }
                        }).inject(Container);
                    });
                });
            }).then(function () {
                return self.$openCategory();
            }).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * Open communication
         *
         * @return {Promise<T>}
         */
        openCommunication: function () {
            var self = this;

            this.Loader.show();
            this.getCategory('communication').setActive();

            return this.$closeCategory().then(function (Container) {
                return new Promise(function (resolve) {
                    require([
                        'package/quiqqer/order/bin/backend/controls/panels/order/Communication'
                    ], function (Communication) {
                        new Communication({
                            orderId: self.getAttribute('orderId'),
                            events : {
                                onLoad: resolve
                            }
                        }).inject(Container);
                    });
                });
            }).then(function () {
                return self.$openCategory();
            }).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * Opens the history category
         *
         * @return {Promise<T>}
         */
        openHistory: function () {
            var self = this;

            this.Loader.show();
            this.getCategory('history').setActive();

            return this.$closeCategory().then(function (Container) {
                return Promise.all([
                    Orders.getOrderHistory(self.getAttribute('orderId')),
                    Container
                ]);
            }).then(function (result) {
                new Comments({
                    comments: result[0]
                }).inject(result[1]);
            }).then(function () {
                return self.$openCategory();
            }).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * Open articles
         */
        openArticles: function () {
            var self = this;

            this.Loader.show();
            this.getCategory('articles').setActive();

            return this.$closeCategory().then(function (Container) {
                return new Promise(function (resolve, reject) {
                    require([
                        'package/quiqqer/erp/bin/backend/controls/articles/ArticleList',
                        'package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary'
                    ], function (ArticleList, Summary) {
                        Container.setStyle('height', '100%');

                        self.$ArticleList = new ArticleList({
                            currency: self.getAttribute('currency'),
                            styles  : {
                                height: 'calc(100% - 120px)'
                            }
                        }).inject(Container);

                        if (self.$serializedList) {
                            self.$ArticleList.unserialize(self.$serializedList);
                        }

                        self.$ArticleListSummary = new Summary({
                            currency: self.getAttribute('currency'),
                            List    : self.$ArticleList,
                            styles  : {
                                bottom  : -20,
                                left    : 0,
                                opacity : 0,
                                position: 'absolute'
                            }
                        }).inject(Container.getParent());

                        moofx(self.$ArticleListSummary.getElm()).animate({
                            bottom : 0,
                            opacity: 1
                        });

                        self.$AddProduct.show();
                        self.$AddSeparator.show();
                        self.$SortSeparator.show();
                        self.$ArticleSort.show();

                        resolve();
                    }, reject);
                });
            }).then(function () {
                return self.$openCategory();
            }).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * Open the post / invoice creation dialog
         */
        openPostDialog: function () {
            var self = this;

            new QUIConfirm({
                title      : QUILocale.get(lg, 'dialog.order.post.title'),
                text       : QUILocale.get(lg, 'dialog.order.post.text'),
                information: QUILocale.get(lg, 'dialog.order.post.information', {
                    id: this.getAttribute('orderId')
                }),
                icon       : 'fa fa-money',
                texticon   : 'fa fa-money',
                maxHeight  : 400,
                maxWidth   : 600,
                autoclose  : false,
                ok_button  : {
                    text     : QUILocale.get(lg, 'panel.btn.createInvoice'),
                    textimage: 'fa fa-money'
                },
                events     : {
                    onSubmit: function (Win) {
                        Win.Loader.show();

                        Orders.postOrder(self.getAttribute('orderId')).then(function (invoiceId) {
                            require([
                                'package/quiqqer/invoice/bin/backend/controls/panels/Invoice',
                                'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice',
                                'package/quiqqer/invoice/bin/Invoices',
                                'utils/Panels'
                            ], function (InvoicePanel, TemporaryInvoice, Invoices, PanelUtils) {
                                // invoiceId
                                Invoices.get(invoiceId).then(function (invoice) {
                                    var Panel;
                                    if (invoice.type === 2) {
                                        Panel = new TemporaryInvoice({
                                            invoiceId: invoiceId
                                        });
                                    } else {
                                        Panel = new InvoicePanel({
                                            invoiceId: invoiceId
                                        });
                                    }

                                    PanelUtils.openPanelInTasks(Panel);
                                    Win.close();
                                });


                            }, function () {
                                Win.close();
                            });
                        }).then(function () {
                            Win.Loader.show();
                        }).catch(function (err) {
                            if (typeof err.getMessage === 'function') {
                                QUI.getMessageHandler().then(function (MH) {
                                    MH.addError(err.getMessage());
                                });
                            }
                            Win.Loader.hide();
                        });
                    }
                }
            }).open();
        },

        /**
         * Opens the delete dialog
         */
        openDeleteDialog: function () {
            var self = this;

            new QUIConfirm({
                title      : QUILocale.get(lg, 'dialog.order.delete.title'),
                text       : QUILocale.get(lg, 'dialog.order.delete.text'),
                information: QUILocale.get(lg, 'dialog.order.delete.information', {
                    id: this.getAttribute('orderId')
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

                        Orders.deleteOrder(self.getAttribute('orderId')).then(function () {
                            Win.close();
                        }).then(function () {
                            Win.Loader.show();
                        });
                    }
                }
            }).open();
        },

        /**
         * Copy the order and opens the new copy
         */
        openCopyDialog: function () {
            var self = this;

            new QUIConfirm({
                title      : QUILocale.get(lg, 'dialog.order.copy.title'),
                text       : QUILocale.get(lg, 'dialog.order.copy.text'),
                information: QUILocale.get(lg, 'dialog.order.copy.information', {
                    id: this.getAttribute('orderId')
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

                        var orderId = self.getAttribute('orderId');

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
                            });
                        }).then(function () {
                            Win.Loader.hide();
                        });
                    }
                }
            }).open();
        },

        /**
         * event: on order deletion
         */
        $onOrderDelete: function (Handler, orderId) {
            if (parseInt(this.getAttribute('orderId')) === parseInt(orderId)) {
                this.destroy();
            }
        },

        /**
         * Open the current category
         *
         * @returns {Promise}
         */
        $openCategory: function () {
            var self = this;

            return new Promise(function (resolve) {
                var Container = self.getContent().getElement('.container');

                if (!Container) {
                    resolve();
                    return;
                }

                moofx(Container).animate({
                    opacity: 1,
                    top    : 0
                }, {
                    duration: 200,
                    callback: resolve
                });
            });
        },

        /**
         * Close the current category
         *
         * @returns {Promise}
         */
        $closeCategory: function () {
            var self = this;

            this.getContent().setStyle('padding', 0);

            // unload
            this.$unLoadCategory();

            if (this.$AddProduct) {
                this.$AddProduct.hide();
                this.$AddSeparator.hide();
                this.$SortSeparator.hide();
                this.$ArticleSort.hide();
            }

            if (this.$Comments) {
                this.$Comments.destroy();
                this.$Comments = null;
            }

            if (this.$ArticleListSummary) {
                moofx(this.$ArticleListSummary.getElm()).animate({
                    bottom : -20,
                    opacity: 0
                }, {
                    duration: 250,
                    callback: function () {
                        this.$ArticleListSummary.destroy();
                        this.$ArticleListSummary = null;
                    }.bind(this)
                });
            }

            return new Promise(function (resolve) {
                var Container = this.getContent().getElement('.container');

                if (!Container) {
                    Container = new Element('div', {
                        'class': 'container',
                        styles : {
                            height  : '100%',
                            opacity : 0,
                            position: 'relative',
                            top     : -50
                        }
                    }).inject(this.getContent());
                }

                moofx(Container).animate({
                    opacity: 0,
                    top    : -50
                }, {
                    duration: 200,
                    callback: function () {
                        if (self.$AddressDelivery) {
                            self.$AddressDelivery.destroy();
                        }

                        if (self.$AddressInvoice) {
                            self.$AddressInvoice.destroy();
                        }

                        Container.set('html', '');
                        Container.setStyle('padding', 20);

                        resolve(Container);
                    }.bind(this)
                });
            }.bind(this));
        },

        /**
         * helper for unloading the data
         * drop the data into the order
         */
        $unLoadCategory: function () {
            var Content          = this.getContent(),
                deliverAddress   = Content.getElement('[name="differentDeliveryAddress"]'),
                PaymentForm      = Content.getElement('form[name="payment"]'),
                ProcessingStatus = Content.getElement('[name="status"]'),
                ShippingStatus   = Content.getElement('[name="shippingStatus"]'),
                Currency         = Content.getElement('[name="currency"]');

            if (this.$AddressInvoice) {
                this.setAttribute('addressInvoice', this.$AddressInvoice.getValue());
            }

            if (this.$AddressDelivery) {
                this.setAttribute('addressDelivery', this.$AddressDelivery.getValue());
            }

            if (deliverAddress && deliverAddress.checked === false) {
                this.setAttribute('addressDelivery', {});
            }

            if (this.$ArticleList) {
                this.setAttribute('articles', this.$ArticleList.save());
                this.$serializedList = this.$ArticleList.serialize();
            }

            // processing status
            if (ProcessingStatus) {
                this.setAttribute('status', parseInt(ProcessingStatus.value));
            }

            // processing status
            if (ShippingStatus) {
                this.setAttribute('shippingStatus', parseInt(ShippingStatus.value));
            }

            // payments
            if (PaymentForm) {
                this.setAttribute('paymentId', PaymentForm.elements.paymentId.value);
            }

            if (Currency) {
                this.setAttribute('currency', Currency.value);
            }

            // customer
            if (this.$Customer) {
                var customer = this.getAttribute('customer'),
                    EUVAT    = Content.getElement('[name="quiqqer.erp.euVatId"]'),
                    TaxNo    = Content.getElement('[name="quiqqer.erp.taxId"]');

                if (typeOf(customer) !== 'object') {
                    customer = {};
                }

                if (!customer.hasOwnProperty('quiqqer.erp.euVatId')) {
                    customer['quiqqer.erp.euVatId'] = '';
                }

                if (!customer.hasOwnProperty('quiqqer.erp.taxId')) {
                    customer['quiqqer.erp.taxId'] = '';
                }

                if (EUVAT) {
                    customer['quiqqer.erp.euVatId'] = EUVAT.value;
                }

                if (TaxNo) {
                    customer['quiqqer.erp.taxId'] = TaxNo.value;
                }

                var customerId = parseInt(this.$Customer.getValue()),
                    User       = Users.get(customerId);

                customer.id = customerId;

                if (User.isLoaded()) {
                    customer.username = User.getUsername();
                    customer.name     = User.getName();

                    if (customer['quiqqer.erp.euVatId'] === '') {
                        customer['quiqqer.erp.euVatId'] = User.getAttribute('quiqqer.erp.euVatId');
                    }

                    if (customer['quiqqer.erp.taxId'] === '') {
                        customer['quiqqer.erp.taxId'] = User.getAttribute('quiqqer.erp.taxId');
                    }
                }

                this.setAttribute('customer', customer);
            }
        },

        //endregion categories

        /**
         * Toggle the article sorting
         */
        toggleSort: function () {
            this.$ArticleList.toggleSorting();

            if (this.$ArticleList.isSortingEnabled()) {
                this.$ArticleSort.setActive();
                return;
            }

            this.$ArticleSort.setNormal();
        },

        /**
         * Opens the product search
         *
         * @todo only if products are installed
         */
        openProductSearch: function () {
            var self = this;

            this.$AddProduct.setAttribute('textimage', 'fa fa-spinner fa-spin');

            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/erp/bin/backend/controls/articles/product/AddProductWindow',
                    'package/quiqqer/erp/bin/backend/controls/articles/Article'
                ], function (Win, Article) {
                    new Win({
                        user  : self.$AddressInvoice.getValue(),
                        events: {
                            onSubmit: function (Win, article) {
                                var Instance = new Article(article);

                                if ("calculated_vatArray" in article) {
                                    Instance.setVat(article.calculated_vatArray.vat);
                                }

                                self.$ArticleList.addArticle(Instance);
                                resolve(Instance);
                            }
                        }
                    }).open();

                    self.$AddProduct.setAttribute('textimage', 'fa fa-plus');
                });
            });
        },

        /**
         * Prompt for a customer notification if the order status has changed
         *
         * @param {Number} statusId
         * @return {Promise}
         */
        $dialogStatusChangeNotification: function (statusId) {
            if (this.$initialStatus === statusId || !statusId) {
                return Promise.resolve(false);
            }

            var NotifyTextEditor;
            var self                      = this;
            var notificationConfirmOpened = false;

            return new Promise(function (resolve) {
                var onNotifyConfirmSubmit = function (Win) {
                    if (notificationConfirmOpened) {
                        resolve(NotifyTextEditor.getContent());
                        Win.close();
                        return;
                    }

                    notificationConfirmOpened = true;

                    Win.Loader.show();

                    var SubmitBtn = Win.getButton('submit');
                    SubmitBtn.disable();

                    ProcessingStatus.getNotificationText(statusId, self.getAttribute('orderId')).then(
                        function (notificationText) {
                            require([
                                'Editors'
                            ], function (Editors) {
                                Editors.getEditor().then(function (Editor) {
                                    Win.Loader.hide();

                                    Win.setAttribute('maxHeight', 900);
                                    Win.setAttribute('maxWidth', 800);
                                    Win.resize();

                                    SubmitBtn.setAttributes({
                                        text     : QUILocale.get(lg, 'dialog.statusChangeNotification.btn.confirm_message'),
                                        textimage: 'fa fa-check'
                                    });

                                    SubmitBtn.enable();

                                    var NotificationElm = new Element('div', {
                                        'class': 'order-notification',
                                        html   : '<span>' + QUILocale.get(lg, 'dialog.statusChangeNotification.notification.label') + '</span>'
                                    }).inject(Win.getContent());

                                    Editor.inject(NotificationElm);
                                    Editor.setContent(notificationText);

                                    NotifyTextEditor = Editor;
                                });
                            });
                        }
                    );
                };

                new QUIConfirm({
                    title        : QUILocale.get(lg, 'dialog.statusChangeNotification.title'),
                    text         : QUILocale.get(lg, 'dialog.statusChangeNotification.text'),
                    information  : '',
                    icon         : 'fa fa-envelope',
                    texticon     : 'fa fa-envelope',
                    maxHeight    : 275,
                    maxWidth     : 600,
                    autoclose    : false,
                    ok_button    : {
                        text     : QUILocale.get(lg, 'dialog.statusChangeNotification.btn.confirm'),
                        textimage: 'fa fa-envelope'
                    },
                    cancel_button: {
                        text     : QUILocale.get(lg, 'dialog.statusChangeNotification.btn.cancel'),
                        textimage: 'fa fa-close'
                    },
                    events       : {
                        onOpen  : function (Win) {
                            // get current status title
                            var statusTitle = self.$Elm.getElement(
                                'select[name="status"] option[value="' + statusId + '"]'
                            ).innerHTML;

                            Win.setAttribute('information', QUILocale.get(lg, 'dialog.statusChangeNotification.information', {
                                statusTitle: statusTitle
                            }));
                        },
                        onSubmit: onNotifyConfirmSubmit,
                        onCancel: function () {
                            return resolve(false);
                        }
                    }
                }).open();
            });
        },

        /**
         * Edit the ordered date
         */
        $editDate: function () {
            var self = this;

            new QUIConfirm({
                title      : QUILocale.get(lg, 'dialog.edit.date.title'),
                text       : '',
                information: '',
                icon       : 'fa fa-calendar',
                texticon   : 'fa fa-calendar',
                maxHeight  : 275,
                maxWidth   : 600,
                events     : {
                    onOpen  : function (Win) {
                        var Content = Win.getContent();

                        Content.addClass('order-edit-date');
                        Content.set('html', Mustache.render(templateChangeDate, {
                            text: QUILocale.get(lg, 'dialog.edit.date.text')
                        }));

                        var D = new Date(self.getAttribute('cDate'));
                        D.setMinutes(D.getMinutes() - D.getTimezoneOffset());

                        Content.getElement('input').value = D.toISOString().slice(0, 16);
                        Content.getElement('form').addEvent('submit', function (e) {
                            e.stop();
                        });
                    },
                    onSubmit: function (Win) {
                        var Content = Win.getContent();
                        var value   = Content.getElement('input').value;

                        var D = new Date(value);
                        var p = D.toISOString().split('T');
                        var d = p[0] + ' ' + p[1].split('.')[0];

                        self.setAttribute('cDate', d);
                        self.getBody().getElements('[name="date"]').set('value', d);
                    }
                }
            }).open();
        },

        //region shipping

        /**
         * Shipping init
         * - shipping status render and building
         *
         * @return {Promise}
         */
        $initShippingStatus: function () {
            if (!shippingInstalled) {
                return Promise.resolve();
            }

            var self    = this,
                Content = this.getContent();

            // build shipping status stuff
            Content.getElement('.order-shipping').setStyle('display', null);

            // order status
            var StatusSelect = Content.getElement('[name="shippingStatus"]');
            var StatusColor  = Content.getElement('.order-data-shipping-status-field-colorPreview');

            StatusSelect.addEvent('change', function () {
                var Option = StatusSelect.getElement('[value="' + this.value + '"]');

                if (Option) {
                    StatusColor.setStyle('backgroundColor', Option.get('data-color'));
                } else {
                    StatusColor.setStyle('backgroundColor', '');
                }
            });

            return new Promise(function (resolve) {
                require(['package/quiqqer/shipping/bin/backend/ShippingStatus'], function (ShippingStatus) {
                    ShippingStatus.getList().then(function (statusList) {
                        statusList = statusList.data;

                        new Element('option', {
                            html        : '',
                            value       : '',
                            'data-color': ''
                        }).inject(StatusSelect);

                        for (var i = 0, len = statusList.length; i < len; i++) {
                            new Element('option', {
                                html        : statusList[i].title,
                                value       : statusList[i].id,
                                'data-color': statusList[i].color
                            }).inject(StatusSelect);
                        }

                        StatusSelect.disabled = false;
                        StatusSelect.value    = self.getAttribute('shippingStatus');
                        StatusSelect.fireEvent('change');

                        if (self.$initialShippingStatus === false) {
                            self.$initialShippingStatus = parseInt(self.getAttribute('shippingStatus'));
                        }
                    }).then(resolve);
                });
            });
        },

        /**
         * Prompt for a customer notification if the order shipping status has changed
         *
         * @param {Number} statusId
         *
         * @return {Promise}
         */
        $dialogShippingStatusChangeNotification: function (statusId) {
            if (!shippingInstalled) {
                return Promise.resolve(false);
            }

            if (this.$initialShippingStatus === statusId || !statusId) {
                return Promise.resolve(false);
            }

            var self = this;

            var NotifyTextEditor;
            var notificationConfirmOpened = false;

            return new Promise(function (resolve) {
                var onNotifyConfirmSubmit = function (Win) {
                    if (notificationConfirmOpened) {
                        resolve(NotifyTextEditor.getContent());
                        Win.close();
                        return;
                    }

                    notificationConfirmOpened = true;

                    Win.Loader.show();

                    var SubmitBtn = Win.getButton('submit');
                    SubmitBtn.disable();

                    require([
                        'package/quiqqer/shipping/bin/backend/ShippingStatus',
                        'Editors'
                    ], function (ShippingStatus, Editors) {
                        ShippingStatus.getNotificationText(
                            statusId,
                            self.getAttribute('orderId')
                        ).then(function (notificationText) {
                            Editors.getEditor().then(function (Editor) {
                                Win.Loader.hide();

                                Win.setAttribute('maxHeight', 900);
                                Win.setAttribute('maxWidth', 800);

                                Win.resize().then(function () {
                                    var NotificationElm = new Element('div', {
                                        'class': 'order-notification',
                                        html   : '<span>' + QUILocale.get(lg, 'dialog.statusChangeNotification.notification.label') + '</span>'
                                    }).inject(Win.getContent());

                                    Editor.setHeight(400);
                                    Editor.inject(NotificationElm);
                                    Editor.setContent(notificationText);

                                    NotifyTextEditor = Editor;

                                    SubmitBtn.setAttributes({
                                        text     : QUILocale.get(lg, 'dialog.statusChangeNotification.btn.confirm_message'),
                                        textimage: 'fa fa-check'
                                    });

                                    SubmitBtn.enable();
                                });
                            });
                        });
                    });
                };

                new QUIConfirm({
                    title        : QUILocale.get(lg, 'dialog.shippingStatusChangeNotification.title'),
                    text         : QUILocale.get(lg, 'dialog.shippingStatusChangeNotification.text'),
                    information  : '',
                    icon         : 'fa fa-envelope',
                    texticon     : 'fa fa-envelope',
                    maxHeight    : 275,
                    maxWidth     : 600,
                    autoclose    : false,
                    ok_button    : {
                        text     : QUILocale.get(lg, 'dialog.shippingStatusChangeNotification.btn.confirm'),
                        textimage: 'fa fa-envelope'
                    },
                    cancel_button: {
                        text     : QUILocale.get(lg, 'dialog.shippingStatusChangeNotification.btn.cancel'),
                        textimage: 'fa fa-close'
                    },
                    events       : {
                        onOpen  : function (Win) {
                            // get current status title
                            var statusTitle = self.$Elm.getElement(
                                'select[name="status"] option[value="' + statusId + '"]'
                            ).innerHTML;

                            Win.setAttribute('information', QUILocale.get(lg, 'dialog.shippingStatusChangeNotification.information', {
                                statusTitle: statusTitle
                            }));
                        },
                        onSubmit: onNotifyConfirmSubmit,
                        onCancel: function () {
                            return resolve(false);
                        }
                    }
                }).open();
            });
        }

        //endregion
    });
});
