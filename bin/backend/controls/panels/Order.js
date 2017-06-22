/**
 * @module package/quiqqer/order/bin/backend/controls/panels/Orders
 *
 * @requnre qui/QUI
 * @requnre qui/controls/desktop/Panel
 * @require qui/controls/buttons/Button
 * @require qui/controls/buttons/ButtonMultiple
 * @require qui/controls/buttons/Separator
 * @requnre package/quiqqer/order/bin/backend/Orders
 * @requnre Locale
 * @requnre Mustache
 * @requnre text!package/quiqqer/order/bin/backend/controls/panels/Order.Data.html
 */
define('package/quiqqer/order/bin/backend/controls/panels/Order', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/ButtonMultiple',
    'qui/controls/buttons/Separator',
    'package/quiqqer/order/bin/backend/Orders',
    'package/quiqqer/invoice/bin/backend/controls/articles/Text',
    'Locale',
    'Mustache',

    'text!package/quiqqer/order/bin/backend/controls/panels/Order.Data.html'

], function (QUI, QUIPanel, QUIButton, QUIButtonMultiple, QUISeparator,
             Orders, TextArticle, QUILocale, Mustache, templateData) {
    "use strict";

    var lg = 'quiqqer/order';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/order/bin/backend/controls/panels/Order',

        Binds: [
            'update',
            'save',
            'refresh',
            'openInfo',
            'openPayments',
            'openArticles',
            'toggleSort',
            '$onCreate',
            '$onResize',
            '$onInject'
        ],

        options: {
            orderId        : false,
            customerId     : false,
            customer       : {},
            addressInvoice : {},
            addressDelivery: {},
            data           : {},
            articles       : []
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                icon : 'fa fa-shopping-cart',
                title: QUILocale.get(lg, 'order.panel.title', {
                    orderId: this.getAttribute('orderId')
                })
            });

            this.$Customer        = null;
            this.$AddressInvoice  = null;
            this.$AddressDelivery = null;

            this.$ArticleList        = null;
            this.$ArticleListSummary = null;

            this.$AddProduct    = null;
            this.$ArticleSort   = null;
            this.$AddSeparator  = null;
            this.$SortSeparator = null;

            this.$serializedList = {};

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
            var self    = this,
                orderId = this.getAttribute('orderId');

            return new Promise(function (resolve, reject) {
                Orders.get(orderId).then(function (data) {
                    console.log(data);
                    self.setAttribute('customerId', data.customerId);
                    self.setAttribute('customer', data.customer);
                    self.setAttribute('data', data.data);
                    self.setAttribute('addressInvoice', data.addressInvoice);
                    self.setAttribute('addressDelivery', data.addressDelivery);

                    if (data.articles) {
                        self.$serializedList = data.articles;
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
            var self    = this,
                orderId = this.getAttribute('orderId');

            this.Loader.show();
            this.$unLoadCategory();

            var data = {
                customerId     : this.getAttribute('customerId'),
                customer       : this.getAttribute('customer'),
                addressInvoice : this.getAttribute('addressInvoice'),
                addressDelivery: this.getAttribute('addressDelivery'),
                data           : this.getAttribute('data'),
                articles       : this.getAttribute('articles')
            };

            console.warn(orderId);
            console.warn(data);
            console.warn(data.articles);

            return new Promise(function (resolve) {
                Orders.updateOrder(orderId, data).then(function () {
                    resolve();
                    self.Loader.hide();
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
                icon  : 'fa fa-trash',
                title : QUILocale.get('quiqqer/quiqqer', 'delete'),
                styles: {
                    'float': 'right'
                },
                events: {
                    onClick: function () {
                    }
                }
            });


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
                icon  : 'fa fa-credit-card',
                name  : 'payment',
                title : QUILocale.get(lg, 'panel.order.category.payment'),
                text  : QUILocale.get(lg, 'panel.order.category.payment'),
                events: {
                    onClick: this.openPayments
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
        },

        /**
         * event : on resize
         */
        $onResize: function () {

        },

        /**
         * event: on inject
         */
        $onInject: function () {
            this.refresh().then(this.openInfo).catch(function (Err) {
                QUI.getMessageHandler().then(function (MH) {
                    MH.addError(Err.getMessage());
                });
            }.bind(this));
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
                        textAddresses           : QUILocale.get(lg, 'address'),
                        textCustomer            : QUILocale.get(lg, 'customer'),
                        textCompany             : QUILocale.get(lg, 'company'),
                        textStreet              : QUILocale.get(lg, 'street'),
                        textZip                 : QUILocale.get(lg, 'zip'),
                        textCity                : QUILocale.get(lg, 'city'),
                        textCountry             : QUILocale.get(lg, 'country'),
                        textOrderData           : QUILocale.get(lg, 'panel.order.data.title'),
                        textOrderDate           : QUILocale.get(lg, 'panel.order.data.date'),
                        textOrderedBy           : QUILocale.get(lg, 'panel.order.data.orderedBy')
                    })
                });

                return QUI.parse(Container);
            }).then(function () {
                var Content        = self.getContent(),
                    deliverAddress = Content.getElement('[name="differentDeliveryAddress"]');

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
                }

                if (self.getAttribute('addressInvoice')) {
                    self.$AddressInvoice.setValue(self.getAttribute('addressInvoice'));
                }

                if (self.getAttribute('addressDelivery')) {
                    self.$AddressDelivery.setValue(self.getAttribute('addressDelivery'));

                    deliverAddress.checked = true;

                    deliverAddress.getParent('table')
                                  .getElements('.closable')
                                  .setStyle('display', null);
                }

                return self.$openCategory();
            }).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * Open payment informations
         */
        openPayments: function () {
            var self = this;

            this.Loader.show();
            this.getCategory('payment').setActive();

            return this.$closeCategory().then(function (Container) {


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

            console.log('articles');

            return this.$closeCategory().then(function (Container) {
                return new Promise(function (resolve, reject) {
                    require([
                        'package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList',
                        'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Summary'
                    ], function (ArticleList, Summary) {
                        Container.setStyle('height', '100%');

                        self.$ArticleList = new ArticleList({
                            styles: {
                                height: 'calc(100% - 120px)'
                            }
                        }).inject(Container);

                        if (self.$serializedList) {
                            self.$ArticleList.unserialize(self.$serializedList);
                        }

                        self.$ArticleListSummary = new Summary({
                            List  : self.$ArticleList,
                            styles: {
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
            var Content        = this.getContent(),
                deliverAddress = Content.getElement('[name="differentDeliveryAddress"]');

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
                    'package/quiqqer/invoice/bin/backend/controls/panels/product/AddProductWindow',
                    'package/quiqqer/invoice/bin/backend/controls/articles/Article'
                ], function (Win, Article) {
                    new Win({
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
        }
    });
});