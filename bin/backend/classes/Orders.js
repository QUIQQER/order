/**
 * @module package/quiqqer/order/bin/backend/classes/Orders
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
define('package/quiqqer/order/bin/backend/classes/Orders', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'

], function (QUI, QUIDOM, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/quiqqer/order/bin/backend/classes/Orders',

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
                QUIAjax.get('package_quiqqer_order_ajax_backend_get', resolve, {
                    'package': 'quiqqer/order',
                    orderId  : orderId,
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Return orders for a grid
         *
         * @param {Object} params - Grid params
         * @returns {Promise}
         */
        getList: function (params) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_backend_list', resolve, {
                    'package': 'quiqqer/order',
                    params   : JSON.encode(params),
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Search orders
         *
         * @param {Object} params - Grid Query Params
         * @param {Object} filter - Filter
         * @returns {Promise}
         */
        search: function (params, filter) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_backend_search', resolve, {
                    'package': 'quiqqer/order',
                    params   : JSON.encode(params),
                    filter   : JSON.encode(filter),
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Create a new order
         *
         * @returns {Promise}
         */
        createOrder: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_create', function (newId) {
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
         * Delete an order
         *
         * @param {String|Number} orderId
         * @returns {Promise}
         */
        deleteOrder: function (orderId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_delete', function () {
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
         * Delete an order
         *
         * @param {String|Number} orderId
         * @returns {Promise}
         */
        copyOrder: function (orderId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_copy', function (newOrderId) {
                    self.fireEvent('orderCopy', [self, newOrderId, orderId]);
                    resolve(newOrderId, orderId);
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
         * Update an order
         *
         * @param {String} orderId
         * @param {Object} data
         * @returns {Promise}
         */
        updateOrder: function (orderId, data) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_update', function () {
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
         * Add a comment to the order
         *
         * @param {String} orderId
         * @param {String} message
         * @returns {Promise}
         */
        addComment: function (orderId, message) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_addComment', function () {
                    self.fireEvent('orderAddComment', [self, orderId, message]);
                    resolve();
                }, {
                    'package': 'quiqqer/order',
                    orderId  : orderId,
                    message  : message,
                    onError  : reject,
                    showError: false
                });
            });
        }
    });
});