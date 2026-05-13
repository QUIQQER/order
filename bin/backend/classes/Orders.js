/**
 * @module package/quiqqer/order/bin/backend/classes/Orders
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

], function(QUI, QUIDOM, QUIAjax) {
    'use strict';

    return new Class({

        Extends: QUIDOM,
        Type: 'package/quiqqer/order/bin/backend/classes/Orders',

        initialize: function(options) {
            this.parent(options);
        },

        /**
         * Return orders for a grid
         *
         * @param {Number} orderId - Grid params
         * @returns {Promise}
         */
        get: function(orderId) {
            return new Promise(function(resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_backend_get', resolve, {
                    'package': 'quiqqer/order',
                    orderId: orderId,
                    onError: reject,
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
        getList: function(params) {
            return new Promise(function(resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_backend_list', resolve, {
                    'package': 'quiqqer/order',
                    params: JSON.encode(params),
                    onError: reject,
                    showError: false
                });
            });
        },

        /**
         * Return the article html from an specific order
         *
         * @param {Number} orderId
         * @return {Promise}
         */
        getArticleHtml: function(orderId) {
            return new Promise(function(resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_backend_getArticleHtml', resolve, {
                    'package': 'quiqqer/order',
                    orderId: orderId,
                    onError: reject,
                    showError: false
                });
            });
        },

        /**
         * Return the combined history of the order
         * - history, transaction, comments
         *
         * @param {String} orderId
         * @return {Promise}
         */
        getOrderHistory: function(orderId) {
            return new Promise(function(resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_backend_getHistory', resolve, {
                    'package': 'quiqqer/order',
                    orderId: orderId,
                    onError: reject,
                    showError: false
                });
            });
        },

        /**
         * Return a preview of an order (html)
         *
         * @param orderId
         * @param onlyArticles
         * @return {*}
         */
        getOrderPreview: function(orderId, onlyArticles) {
            if (typeof onlyArticles === 'undefined') {
                onlyArticles = 0;
            }

            if (onlyArticles) {
                onlyArticles = 1;
            }


            return new Promise(function(resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_preview', resolve, {
                    'package': 'quiqqer/order',
                    orderId: orderId,
                    onlyArticles: onlyArticles,
                    onError: reject,
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
        search: function(params, filter) {
            return new Promise(function(resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_backend_search', resolve, {
                    'package': 'quiqqer/order',
                    params: JSON.encode(params),
                    filter: JSON.encode(filter),
                    onError: reject,
                    showError: false
                });
            });
        },

        /**
         * Create a new order
         *
         * @returns {Promise}
         */
        createOrder: function() {
            const self = this;

            return new Promise(function(resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_create', function(newId) {
                    self.fireEvent('orderCreate', [
                        self,
                        newId
                    ]);
                    resolve(newId);
                }, {
                    'package': 'quiqqer/order',
                    onError: reject,
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
        deleteOrder: function(orderId) {
            return new Promise((resolve, reject) => {
                QUIAjax.post('package_quiqqer_order_ajax_backend_delete', () => {
                    this.fireEvent('orderDelete', [this, orderId]);
                    resolve();
                }, {
                    'package': 'quiqqer/order',
                    orderId: orderId,
                    onError: reject,
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
        copyOrder: function(orderId) {
            const self = this;

            return new Promise(function(resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_copy', function(newOrderId) {
                    self.fireEvent('orderCopy', [
                        self,
                        newOrderId,
                        orderId
                    ]);
                    resolve(newOrderId, orderId);
                }, {
                    'package': 'quiqqer/order',
                    orderId: orderId,
                    onError: reject,
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
        saveOrder: function(orderId, data) {
            return this.updateOrder(orderId, data);
        },

        /**
         * Update an order
         *
         * @param {String} orderId
         * @param {Object} data
         * @returns {Promise}
         */
        updateOrder: function(orderId, data) {
            const self = this;

            return new Promise(function(resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_update', function(result) {
                    self.fireEvent('orderSave', [
                        self,
                        orderId,
                        data
                    ]);

                    resolve(result);
                }, {
                    'package': 'quiqqer/order',
                    orderId: orderId,
                    data: JSON.encode(data),
                    onError: reject,
                    showError: false
                });
            });
        },

        /**
         *
         * @param {String} orderId
         * @returns {Promise}
         */
        postOrder: function(orderId) {
            const self = this;

            return new Promise(function(resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_post', function(invoiceId) {
                    self.fireEvent('orderPost', [
                        self,
                        orderId
                    ]);
                    resolve(invoiceId);
                }, {
                    'package': 'quiqqer/order',
                    orderId: orderId,
                    onError: reject,
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
        addComment: function(orderId, message) {
            const self = this;

            return new Promise(function(resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_addComment', function() {
                    self.fireEvent('orderAddComment', [
                        self,
                        orderId,
                        message
                    ]);
                    resolve();
                }, {
                    'package': 'quiqqer/order',
                    orderId: orderId,
                    message: message,
                    onError: reject,
                    showError: false
                });
            });
        },

        /**
         * Create a new temporary invoice from the order
         *
         * @param {Number} orderId
         * @returns {Promise}
         */
        createInvoice: function(orderId) {
            const self = this;

            return new Promise(function(resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_createInvoice', function(newInvoiceId) {
                    self.fireEvent('orderInvoiceCreate', [
                        self,
                        newInvoiceId
                    ]);
                    resolve(newInvoiceId);
                }, {
                    'package': 'quiqqer/order',
                    onError: reject,
                    orderId: orderId,
                    showError: false
                });
            });
        },

        /**
         * Create a new temporary invoice from the order
         *
         * @param {Number} orderId
         * @returns {Promise}
         */
        createSalesOrder: function(orderId) {
            const self = this;

            return new Promise(function(resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_createSalesOrder', function(salesOrderHash) {
                    self.fireEvent('orderSalesOrderCreate', [
                        self,
                        salesOrderHash
                    ]);
                    resolve(salesOrderHash);
                }, {
                    'package': 'quiqqer/order',
                    onError: reject,
                    orderId: orderId,
                    showError: false
                });
            });
        },

        /**
         * Add a payment to an order
         *
         * @param {Number|String} orderId - id or hash
         * @param {Number} amount
         * @param {String} paymentMethod
         * @param {Number|String} [date]
         */
        addPaymentToOrder: function(orderId, amount, paymentMethod, date) {
            const self = this;

            date = date || false;

            return new Promise(function(resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_addPayment', function(id) {
                    self.fireEvent('addPaymentToOrder', [
                        self,
                        orderId,
                        id
                    ]);
                    resolve(id);
                }, {
                    'package': 'quiqqer/order',
                    orderId: orderId,
                    amount: amount,
                    paymentMethod: paymentMethod,
                    date: date,
                    onError: reject,
                    showError: false
                });
            });
        },

        /**
         * Add a payment to an order
         *
         * @param {String} orderHash
         * @param {String} txId
         * @
         */
        linkTransaction: function(orderHash, txId) {
            return new Promise((resolve, reject) => {
                QUIAjax.post('package_quiqqer_order_ajax_backend_linkTransaction', resolve, {
                    'package': 'quiqqer/order',
                    orderHash: orderHash,
                    txId: txId,
                    onError: reject,
                    showError: true
                });
            });
        }
    });
});