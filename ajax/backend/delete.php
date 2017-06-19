<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_delete
 */

/**
 * Delete an order
 *
 * @param string|integer $orderId - ID of the order
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_delete',
    function ($orderId) {

    },
    array('orderId'),
    'Permission::checkAdminUser'
);
