<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_update
 */

/**
 * Update an order
 *
 * @param string|integer $orderId - ID of the order
 * @param string $data - JSON query data
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_update',
    function ($orderId, $data) {

    },
    array('orderId', 'data'),
    'Permission::checkAdminUser'
);
