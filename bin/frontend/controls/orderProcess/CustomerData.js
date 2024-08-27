/**
 * @module package/quiqqer/order/bin/frontend/controls/orderProcess/CustomerData
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/order/bin/frontend/controls/orderProcess/CustomerData', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/order/bin/frontend/Orders',
    'Ajax',
    'Locale'

], function(QUI, QUIControl, Orders, QUIAjax, QUILocale) {
    'use strict';

    const lg = 'quiqqer/order';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/order/bin/frontend/controls/orderProcess/CustomerData',

        Binds: [
            'openAddressEdit',
            'closeAddressEdit',
            '$onBusinessTypeChange',
            'validateVatId',
            '$onCountryChange'
        ],

        initialize: function(options) {
            this.parent(options);

            this.$CheckTimeout = null;
            this.$Close = null;
            this.EditButton = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function() {
            const self = this,
                BusinessType = this.getElm().getElements('[name="businessType"]'),
                businessFields = this.getElm().getElements('[name="company"],[name="vatId"],[name="chUID"]'),
                VatId = this.getElm().getElements('[name="vatId"]');

            this.EditButton = this.getElm().getElements('[name="open-edit"]');

            this.EditButton.addEvent('click', this.openAddressEdit);
            this.EditButton.set('disabled', false);

            this.$Close = this.getElm().getElement('.quiqqer-order-customerData-edit-close');
            this.$Close.addEvent('click', this.closeAddressEdit);

            const EditContainer = this.getElm().getElement('.quiqqer-order-customerData-edit');

            EditContainer.getElements('input,select').addEvent('change', function() {
                if (self.isValid()) {
                    self.$Close.setStyle('display', null);
                    return;
                }

                self.$Close.setStyle('display', 'none');
            });

            if (BusinessType) {
                BusinessType.addEvent('change', this.$onBusinessTypeChange);

                businessFields.addEvent('change', () => {
                    for (let i = 0, len = businessFields.length; i < len; i++) {
                        if (businessFields[i].value !== '') {
                            BusinessType[0].value = 'b2b';
                            this.openAddressEdit().catch(() => {
                            });
                            break;
                        }
                    }
                });
            }

            // VatId.addEvent('change', this.$onVatIdChange);
            // VatId.addEvent('keyup', this.$onVatIdChange);
            VatId.addEvent('blur', this.$onVatIdChange);

            const val = parseInt(this.getElm().get('data-validate'));

            if (isNaN(val) || !val) {
                this.openAddressEdit().catch(function(err) {
                    console.error(err);
                });
            }

            // country edit
            const Country = self.getElm().getElement('[name="country"]');

            if (Country.get('data-qui') && !Country.get('data-quiid')) {
                QUI.parse(this.getElm()).then(function() {
                    QUI.Controls.getById(Country.get('data-quiid')).addEvent('onCountryChange', self.$onCountryChange);
                });
            } else {
                if (Country.get('data-quiid')) {
                    QUI.Controls.getById(Country.get('data-quiid')).addEvent('onCountryChange', self.$onCountryChange);
                } else {
                    Country.addEvent('change', self.$onCountryChange);
                }
            }

            this.$onCountryChange();
        },

        /**
         * Save the address via ajax
         *
         * @return {Promise}
         */
        save: function() {
            // address data
            const Parent = this.getElm().getElement('.quiqqer-order-customerData-edit');
            const formElms = Parent.getElements('input,select');
            const address = {};

            let i, len, forElement;

            for (i = 0, len = formElms.length; i < len; i++) {
                forElement = formElms[i];

                if (forElement.name === '') {
                    continue;
                }

                address[forElement.name] = forElement.value;
            }

            // get order process for loader
            const self = this;

            let Loader = null,
                OrderProcess = null;

            const OrderProcessNode = this.getElm().getParent(
                '[data-qui="package/quiqqer/order/bin/frontend/controls/OrderProcess"]'
            );

            if (OrderProcessNode) {
                OrderProcess = QUI.Controls.getById(OrderProcessNode.get('data-quiid'));
                Loader = OrderProcess.Loader;
            }

            if (OrderProcess) {
                if (OrderProcess.validateStep() === false) {
                    return Promise.reject();
                }
            }

            if (Loader) {
                Loader.show();
            }

            if (address.country === 'CH') {
                address.vatId = '';
            } else {
                address.chUID = '';
            }

            // save the data
            return new Promise(function(resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_frontend_order_address_save', function(valid) {
                    if (Loader) {
                        Loader.hide();
                    }

                    // validate
                    if (valid) {
                        if (OrderProcess) {
                            OrderProcess.refreshCurrentStep();
                        } else {
                            self.closeAddressEdit();
                        }
                    }

                    resolve();
                }, {
                    'package': 'quiqqer/order',
                    addressId: address.addressId,
                    data: JSON.encode(address),
                    onError: function(err) {
                        QUI.getMessageHandler().then(function(MH) {
                            MH.addError(err.getMessage());
                        });

                        if (Loader) {
                            Loader.hide();
                        }

                        reject();
                    }
                });
            });
        },

        /**
         * Open the address edit
         *
         * @param {DOMEvent} [event]
         * @return {Promise}
         */
        openAddressEdit: function(event) {
            if (typeOf(event) === 'domevent') {
                event.stop();
            }

            moofx(this.EditButton).animate({
                opacity: 0,
                visibility: 'hidden'
            });

            const self = this,
                Elm = this.getElm(),
                Container = Elm.getElement('.quiqqer-order-customerData__container'),
                DisplayContainer = Elm.getElement('.quiqqer-order-customerData-display'),
                EditWrapper = Elm.getElement('.quiqqer-order-customerData__edit-wrapper'),
                Edit = Elm.getElement('.quiqqer-order-customerData-edit');

            Container.style.height = Container.offsetHeight + 'px';

            const BusinessType = Elm.getElement('[name="businessType"]');
            const OrderProcess = self.$getOrderProcess();

            if (BusinessType) {
                self.$onBusinessTypeChange({
                    target: BusinessType
                });
            }

            if (OrderProcess) {
                OrderProcess.resize();
            }

            if (this.isValid() === false) {
                this.$Close.setStyle('display', 'none');
            }

            return this.$fx(DisplayContainer, {
                opacity: 0
            }).then(function() {
                DisplayContainer.style.display = 'none';

                return self.$fx(Container, {
                    height: Edit.getComputedSize().height
                });
            }).then(function() {
                // save event
                Edit.getElement('[type="submit"]').addEvent('click', function(event) {
                    event.stop();
                    self.save().catch(function() {
                        // nothing
                    });
                });

                EditWrapper.style.height = null;

                self.$Close.style.display = null;
                moofx(self.$Close).animate({
                    opacity: 1,
                    visibility: null
                });

                return self.$fx(Edit, {
                    opacity: 1
                });
            }).then(function() {
                Container.setStyle('height', null);

                if (OrderProcess) {
                    OrderProcess.resize();
                }
            });
        },

        /**
         * Close the address edit
         *
         * @param {DOMEvent} [event]
         * @return {Promise}
         */
        closeAddressEdit: function(event) {
            if (typeOf(event) === 'domevent') {
                event.stop();
            }

            const self = this,
                Elm = this.getElm(),
                Container = Elm.getElement('.quiqqer-order-customerData__container'),
                DisplayContainer = Elm.getElement('.quiqqer-order-customerData-display'),
                EditWrapper = Elm.getElement('.quiqqer-order-customerData__edit-wrapper'),
                Edit = Elm.getElement('.quiqqer-order-customerData-edit');

            moofx(this.$Close).animate({
                opacity: 0,
                visibility: 'hidden'
            }, {
                callback: function() {
                    self.$Close.style.display = 'none';
                }
            });

            const OrderProcess = self.$getOrderProcess();

            Container.style.height = Container.offsetHeight + 'px';

            return this.$fx(Edit, {
                opacity: 0
            }).then(function() {

                DisplayContainer.style.opacity = 0;
                DisplayContainer.style.display = null;

                return self.$fx(Container, {
                    height: DisplayContainer.getComputedSize().height
                });
            }).then(function() {
                EditWrapper.style.height = 0;

                moofx(self.EditButton).animate({
                    opacity: 1,
                    visibility: null
                });


                return self.$fx(DisplayContainer, {
                    opacity: 1
                }).then(function() {
                    Container.setStyle('height', null);

                    if (OrderProcess) {
                        OrderProcess.resize();
                    }
                });
            });
        },

        /**
         *
         * @param event
         */
        $onVatIdChange: function(event) {
            const Target = event.target,
                vatId = Target.value;

            if (this.$CheckTimeout) {
                clearTimeout(this.$CheckTimeout);
            }

            this.$CheckTimeout = (function() {
                let Loader = Target.getParent().getElement(
                    '.quiqqer-order-customerData-tax-validation-loader'
                );

                if (!Loader) {
                    Loader = new Element('span', {
                        'class': 'quiqqer-order-customerData-tax-validation-loader fa'
                    }).inject(Target, 'after');
                }

                Loader.removeClass('fa-check');
                Loader.addClass('fa-spinner fa-spin');

                Orders.validateVatId(vatId).then(function(result) {
                    if (result) {
                        Loader.removeClass('fa-spinner fa-spin');
                        Loader.addClass('fa-check');
                        return;
                    }

                    Loader.destroy();
                }).catch(function(err) {
                    if (typeof err !== 'undefined' && typeof err.getMessage === 'function') {
                        QUI.getMessageHandler().then(function(MH) {
                            MH.addError(err.getMessage(), Target);
                        });
                    }

                    Loader.destroy();
                });
            }).delay(300);
        },

        /**
         * event: on business type change
         *
         * @param event
         */
        $onBusinessTypeChange: function(event) {
            const Target = event.target;

            if (Target.nodeName !== 'SELECT') {
                return;
            }

            const businessType = Target.value;
            const Container = this.getElm().querySelector('.bt2-labelContainer'),
                Inner = this.getElm().querySelector('.bt2-labelContainer__inner');

            const Company = this.getElm().getElement('.quiqqer-order-customerData-edit-company');
            const VatId = this.getElm().getElement('.quiqqer-order-customerData-edit-vatId');

            function show()
            {
                if (VatId.getElement('input').value !== '') {
                    VatId.getElement('input').disabled = true;
                    VatId.getElement('input').title = QUILocale.get(lg, 'customer.data.vat.chaning.not.allowed');
                }

                moofx(Container).animate({
                    height: Inner.offsetHeight,
                    opacity: 1
                }, {
                    callback: function() {
//                        Container.style.height = null;
                    }
                });
            }

            function hide()
            {
                moofx(Container).animate({
                    height: 0,
                    opacity: 0
                });
            }

            if (businessType === 'b2c') {
                hide();
            } else {
                show();
            }

            (function() {
                const OrderProcess = this.$getOrderProcess();

                if (OrderProcess) {
                    OrderProcess.resize();
                }
            }).delay(500, this);
        },

        /**
         * event: on country change
         */
        $onCountryChange: function() {
            const VatId = this.getElm().getElements('.quiqqer-order-customerData-edit-vatId');
            const chUID = this.getElm().getElements('.quiqqer-order-customerData-edit-chUID');

            const Country = this.getElm().getElement('[name="country"]');

            if (Country.value === 'CH') {
                VatId.setStyle('display', 'none');
                chUID.setStyle('display', null);
            } else {
                VatId.setStyle('display', null);
                chUID.setStyle('display', 'none');
            }
        },

        /**
         * css fx
         *
         * @param Node
         * @param styles
         * @param options
         */
        $fx: function(Node, styles, options) {
            options = options || {};
            const duration = options.duration || 250;

            return new Promise(function(resolve) {
                moofx(Node).animate(styles, {
                    duration: duration,
                    callback: resolve
                });
            });
        },

        /**
         * Return the parent order process
         *
         * @return {null}
         */
        $getOrderProcess: function() {
            const OrderProcessNode = this.getElm().getParent(
                '[data-qui="package/quiqqer/order/bin/frontend/controls/OrderProcess"]'
            );

            if (!OrderProcessNode) {
                return null;
            }

            const OrderProcess = QUI.Controls.getById(OrderProcessNode.get('data-quiid'));

            if (!OrderProcess) {
                return null;
            }

            return OrderProcess;
        },

        /**
         * Check / validate the step
         * html5 validation
         *
         * @return {boolean}
         */
        isValid: function() {
            const Required = this.getElm().getElements('[required]');

            if (Required.length) {
                let i, len, Field;

                for (i = 0, len = Required.length; i < len; i++) {
                    Field = Required[i];

                    if (Field.getStyle('display') === 'none') {
                        continue;
                    }

                    if (!('checkValidity' in Field)) {
                        continue;
                    }

                    if (Field.checkValidity()) {
                        continue;
                    }

                    return false;
                }
            }

            return true;
        },

        /**
         * validate address
         *
         * @return {Promise}
         */
        validate: function() {
            if (this.isValid() === false) {
                return this.openAddressEdit();
            }

            return Promise.resolve();
        }
    });
});
