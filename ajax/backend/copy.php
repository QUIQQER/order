<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_copy
 */

use QUI\ERP\Order\Handler;

/**
 * Copy an order
 *
 * @return integer
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_copy',
    function ($orderId) {
        $Order = Handler::getInstance()->get($orderId);

        return $Order->copy()->getId();
    },
    ['orderId'],
    'Permission::checkAdminUser'
);
