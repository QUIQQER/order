/**
 * @module package/quiqqer/order/bin/frontend/controls/orderProcess/CustomerData
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/order/bin/frontend/controls/orderProcess/CustomerData', [

    'qui/QUI',
    'qui/controls/Control'

], function (QUI, QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/orderProcess/CustomerData',

        Binds: [
            'openAddressEdit',
            'closeAddressEdit'
        ],

        initialize: function (options) {
            this.parent(options);

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

            EditButton.addEvent('click', this.openAddressEdit);
            EditButton.set('disabled', false);

            CloseEditButton.addEvent('click', this.closeAddressEdit);
        },

        save: function () {

        },

        /**
         * Open the address edit
         *
         * @param {DOMEvent} event
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
