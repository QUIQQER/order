/**
 * @module package/quiqqer/order/bin/frontend/controls/orderProcess/CustomerData
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/order/bin/frontend/controls/orderProcess/CustomerData', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/order/bin/frontend/Orders'

], function (QUI, QUIControl, Orders) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/orderProcess/CustomerData',

        Binds: [
            'openAddressEdit',
            'closeAddressEdit',
            '$onBusinessTypeChange',
            'validateVatId'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$CheckTimeout = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var EditButton      = this.getElm().getElements('[name="open-edit"]');
            var CloseEditButton = this.getElm().getElements('.quiqqer-order-customerData-edit-close');
            var BusinessType    = this.getElm().getElements('[name="businessType"]');
            var VatId           = this.getElm().getElements('[name="vatId"]');

            EditButton.addEvent('click', this.openAddressEdit);
            EditButton.set('disabled', false);

            CloseEditButton.addEvent('click', this.closeAddressEdit);

            if (BusinessType) {
                BusinessType.addEvent('change', this.$onBusinessTypeChange);
            }

            VatId.addEvent('change', this.$onVatIdChange);
            VatId.addEvent('keyup', this.$onVatIdChange);

            var val = parseInt(this.getElm().get('data-validate'));

            if (isNaN(val) || !val) {
                this.openAddressEdit();
            }
        },

        /**
         * Save the address via ajax
         */
        save: function () {

        },

        /**
         * Open the address edit
         *
         * @param {DOMEvent} [event]
         * @return {Promise}
         */
        openAddressEdit: function (event) {
            if (typeOf(event) === 'domevent') {
                event.stop();
            }

            var self             = this,
                Elm              = this.getElm(),
                Container        = Elm.getElement('.quiqqer-order-customerData'),
                DisplayContainer = Elm.getElement('.quiqqer-order-customerData-display'),
                EditContainer    = Elm.getElement('.quiqqer-order-customerData-edit'),
                Header           = Elm.getElement('.quiqqer-order-customerData header');

            var VatId        = Elm.getElement('[name="vatId"]');
            var Company      = Elm.getElement('[name="company"]');
            var BusinessType = Elm.getElement('[name="businessType"]');

            if (VatId.value !== '') {
                VatId.disabled = true;
            }

            if (Company.value !== '') {
                Company.disabled = true;
            }

            if (BusinessType.value !== '') {
                BusinessType.disabled = true;
            }

            return this.$fx(DisplayContainer, {
                opacity: 0
            }).then(function () {
                return self.$fx(Container, {
                    height: EditContainer.getComputedSize().height + Header.getSize().y
                });
            }).then(function () {
                DisplayContainer.setStyle('display', 'none');
                EditContainer.setStyle('opacity', 0);
                EditContainer.setStyle('display', 'inline');

                return self.$fx(EditContainer, {
                    opacity: 1
                });
            }).then(function () {
                self.$onBusinessTypeChange({
                    target: self.getElm().getElement('[name="businessType"]')
                });

                EditContainer.setStyle('display', 'inline');
                Container.setStyle('height', null);
            });
        },

        /**
         * Close the address edit
         *
         * @param {DOMEvent} event
         * @return {Promise}
         */
        closeAddressEdit: function (event) {
            if (typeOf(event) === 'domevent') {
                event.stop();
            }

            var self             = this,
                Elm              = this.getElm(),
                Container        = Elm.getElement('.quiqqer-order-customerData'),
                DisplayContainer = Elm.getElement('.quiqqer-order-customerData-display'),
                EditContainer    = Elm.getElement('.quiqqer-order-customerData-edit'),
                Header           = Elm.getElement('.quiqqer-order-customerData header');

            return this.$fx(EditContainer, {
                opacity: 0
            }).then(function () {
                return self.$fx(Container, {
                    height: DisplayContainer.getComputedSize().height + Header.getSize().y
                });
            }).then(function () {
                EditContainer.setStyles({
                    display: 'none',
                    opacity: null
                });

                DisplayContainer.setStyle('opacity', 0);
                DisplayContainer.setStyle('display', null);

                return self.$fx(DisplayContainer, {
                    opacity: 1
                });
            });
        },

        /**
         *
         * @param event
         */
        $onVatIdChange: function (event) {
            var Target = event.target,
                vatId  = Target.value;

            if (this.$CheckTimeout) {
                clearTimeout(this.$CheckTimeout);
            }

            this.$CheckTimeout = (function () {
                var Loader = Target.getParent().getElement(
                    '.quiqqer-order-customerData-tax-validation-loader'
                );

                if (!Loader) {
                    Loader = new Element('span', {
                        'class': 'quiqqer-order-customerData-tax-validation-loader fa'
                    }).inject(Target, 'after');
                }

                Loader.removeClass('fa-check');
                Loader.addClass('fa-spinner fa-spin');

                Orders.validateVatId(vatId).then(function (result) {
                    if (result) {
                        Loader.removeClass('fa-spinner fa-spin');
                        Loader.addClass('fa-check');
                        return;
                    }

                    Loader.destroy();
                }).catch(function (err) {
                    QUI.getMessageHandler().then(function (MH) {
                        MH.addError(err.getMessage(), Target);
                    });

                    Loader.destroy();
                });
            }).delay(300);
        },

        /**
         * event: on business type change
         *
         * @param event
         */
        $onBusinessTypeChange: function (event) {
            var Target = event.target;

            if (Target.nodeName !== 'SELECT') {
                return;
            }

            var businessType = Target.value;
            var Company      = this.getElm().getElement('.quiqqer-order-customerData-edit-company');
            var VatId        = this.getElm().getElement('.quiqqer-order-customerData-edit-vatId');

            var styles = {
                display : 'inline-block',
                height  : Company.getSize().y,
                overflow: 'hidden',
                opacity : 0,
                position: 'relative'
            };

            Company.setStyles(styles);
            VatId.setStyles(styles);

            function show() {
                moofx([VatId, Company]).animate({
                    height      : Company.getScrollSize().y,
                    marginBottom: 10,
                    opacity     : 1
                }, {
                    duration: 250
                });
            }

            function hide() {
                moofx([VatId, Company]).animate({
                    height : 0,
                    margin : 0,
                    padding: 0,
                    opacity: 0
                }, {
                    duration: 250
                });
            }

            if (businessType === 'b2c') {
                hide();
            } else {
                show();
            }
        },

        /**
         * css fx
         *
         * @param Node
         * @param styles
         * @param options
         */
        $fx: function (Node, styles, options) {
            options      = options || {};
            var duration = options.duration || 250;

            return new Promise(function (resolve) {
                moofx(Node).animate(styles, {
                    duration: duration,
                    callback: resolve
                });
            });
        }
    });
});