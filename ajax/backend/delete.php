<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_delete
 */

use QUI\ERP\Order\Handler;

/**
 * Delete an order
 *
 * @param string|integer $orderId - ID of the order
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_delete',
    function ($orderId) {
        $Order = Handler::getInstance()->get($orderId);
        $Order->delete();
    },
    ['orderId'],
    'Permission::checkAdminUser'
);
