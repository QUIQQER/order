/**
 * @module package/quiqqer/order/bin/backend/classes/ProcessingStatus
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 */
define('package/quiqqer/order/bin/backend/classes/ProcessingStatus', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'

], function (QUI, QUIDOM, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/quiqqer/order/bin/backend/classes/ProcessingStatus',

        initialize: function (options) {
            this.parent(options);
        },

        /**
         * Return the processing status list for a grid
         *
         * @return {Promise}
         */
        getList: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_backend_processingStatus_list', resolve, {
                    'package': 'quiqqer/order',
                    onError  : reject
                });
            });
        },

        /**
         * Return next available ID
         *
         * @return {Promise}
         */
        getNextId: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_backend_processingStatus_getNextId', resolve, {
                    'package': 'quiqqer/order',
                    onError  : reject
                });
            });
        },

        /**
         * Create a new processing status
         *
         * @param {String|Number} id - Processing Status ID
         * @param {String} color
         * @param {Object} title - {de: '', en: ''}
         * @param {Boolean} notification
         * @return {Promise}
         */
        createProcessingStatus: function (id, color, title, notification) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_processingStatus_create', function (result) {
                    require([
                        'package/quiqqer/translator/bin/Translator'
                    ], function (Translator) {
                        Translator.refreshLocale().then(function () {
                            resolve(result);
                        });
                    });
                }, {
                    'package'   : 'quiqqer/order',
                    id          : id,
                    color       : color,
                    title       : JSON.encode(title),
                    notification: notification ? 1 : 0,
                    onError     : reject
                });
            });
        },

        /**
         * Delete a processing status
         *
         * @param {String|Number} id - Processing Status ID
         * @return {Promise}
         */
        deleteProcessingStatus: function (id) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_processingStatus_delete', function () {
                    require([
                        'package/quiqqer/translator/bin/Translator'
                    ], function (Translator) {
                        Translator.refreshLocale().then(function () {
                            resolve();
                        });
                    });
                }, {
                    'package': 'quiqqer/order',
                    id       : id,
                    onError  : reject
                });
            });
        },

        /**
         * Return the status data
         *
         * @param {String|Number} id - Processing Status ID
         * @return {Promise}
         */
        getProcessingStatus: function (id) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_processingStatus_get', resolve, {
                    'package': 'quiqqer/order',
                    id       : id,
                    onError  : reject
                });
            });
        },

        /**
         * Return the status data
         *
         * @param {String|Number} id - Processing Status ID
         * @param {String} color
         * @param {Object} title - {de: '', en: ''}
         * @param {Boolean} notification
         * @return {Promise}
         */
        updateProcessingStatus: function (id, color, title, notification) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_backend_processingStatus_update', resolve, {
                    'package'   : 'quiqqer/order',
                    id          : id,
                    color       : color,
                    title       : JSON.encode(title),
                    onError     : reject,
                    notification: notification ? 1 : 0
                });
            });
        },

        /**
         * Get status change notification text for a specific order
         *
         * @param {Number} id - ProcessingStatus ID
         * @param {Number} orderId - Order ID
         * @return {Promise}
         */
        getNotificationText: function (id, orderId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_backend_processingStatus_getNotificationText', resolve, {
                    'package': 'quiqqer/order',
                    id       : id,
                    orderId  : orderId,
                    onError  : reject
                });
            });
        }
    });
});
