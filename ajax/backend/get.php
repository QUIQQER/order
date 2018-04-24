<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_get
 */

use QUI\ERP\Order\Handler;

/**
 * Returns an order
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_get',
    function ($orderId) {
        return Handler::getInstance()->get($orderId)->toArray();
    },
    ['orderId'],
    'Permission::checkAdminUser'
);
