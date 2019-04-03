/**
 * @module package/quiqqer/order/bin/frontend/classes/Orders
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 *
 * @event onOrderCreate [self, newId]
 * @event onOrderDelete [self, orderId]
 * @event onOrderCopy [self, newOrderId, orderId]
 * @event onOrderSave [self, orderId, data]
 */
define('package/quiqqer/order/bin/frontend/classes/Orders', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'

], function (QUI, QUIDOM, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/quiqqer/order/bin/frontend/classes/Orders',

        initialize: function (options) {
            this.parent(options);
        },

        /**
         * Return orders for a grid
         *
         * @param {Number} orderId - Grid params
         * @returns {Promise}
         */
        get: function (orderId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_get', resolve, {
                    'package': 'quiqqer/order',
                    orderId  : orderId,
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Return orders from the user
         *
         * @param {Object} params - Grid params
         * @returns {Promise}
         */
        getList: function (params) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_list', resolve, {
                    'package': 'quiqqer/order',
                    params   : JSON.encode(params),
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Return the last order
         *
         * @returns {Promise}
         */
        getLastOrder: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_basket_getLastOrder', resolve, {
                    'package': 'quiqqer/order',
                    onError  : reject,
                    showError: false
                });
            });
        },
        /**
         * Return the url of the order process
         *
         * @returns {Promise}
         */
        getOrderProcessUrl: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_basket_getOrderProcessUrl', resolve, {
                    'package': 'quiqqer/order',
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Create an in processing order
         *
         * @returns {Promise}
         */
        createOrder: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_frontend_create', function (newId) {
                    self.fireEvent('orderCreate', [self, newId]);
                    resolve(newId);
                }, {
                    'package': 'quiqqer/order',
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Delete an in processing order
         *
         * @param {String|Number} orderId
         * @returns {Promise}
         */
        deleteOrder: function (orderId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_frontend_delete', function () {
                    self.fireEvent('orderDelete', [self, orderId]);
                    resolve();
                }, {
                    'package': 'quiqqer/order',
                    orderId  : orderId,
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Alias for update
         *
         * @param {String} orderId
         * @param {Object} data
         * @returns {Promise}
         */
        saveOrder: function (orderId, data) {
            return this.updateOrder(orderId, data);
        },

        /**
         * Update an in processing order
         *
         * @param {String} orderId
         * @param {Object} data
         * @returns {Promise}
         */
        updateOrder: function (orderId, data) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_frontend_update', function () {
                    self.fireEvent('orderSave', [self, orderId, data]);
                    resolve();
                }, {
                    'package': 'quiqqer/order',
                    orderId  : orderId,
                    data     : JSON.encode(data),
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Clears the complete order
         *
         * @param orderHash
         * @return {Promise}
         */
        clearOrder: function (orderHash) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_frontend_order_clear', function () {
                    self.fireEvent('orderClear', [self, orderHash]);
                    resolve();
                }, {
                    'package': 'quiqqer/order',
                    orderHash: orderHash,
                    onError  : reject,
                    showError: false
                });
            });

        },

        /**
         * Validate a VAT ID
         *
         * @param {String|Number} vatId
         * @returns {Promise}
         */
        validateVatId: function (vatId) {
            vatId = vatId.trim();

            if (vatId === '') {
                return Promise.reject();
            }

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_frontend_order_address_validateVatId', resolve, {
                    'package': 'quiqqer/order',
                    vatId    : vatId,
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Save (Change) the payment method during a processing step
         *
         * @param {String} orderHash - order hash
         * @param {String|Number} payment - payment id
         * @return {Promise}
         */
        saveProcessingPaymentChange: function (orderHash, payment) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_frontend_order_processing_savePayment', resolve, {
                    'package': 'quiqqer/order',
                    orderHash: orderHash,
                    payment  : payment,
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Remove a product by its order position
         *
         * @param {String} orderHash - order hash
         * @param {Number} pos -
         * @return {Promise}
         */
        removePosition: function (orderHash, pos) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_frontend_order_removePosition', resolve, {
                    'package': 'quiqqer/order',
                    orderHash: orderHash,
                    pos      : pos,
                    onError  : reject,
                    showError: false
                });
            });
        }
    });
});