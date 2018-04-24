<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_post
 */

/**
 * Add a comment to the order
 *
 * @param string|integer $orderId - ID of the order
 * @param string $message - comment message
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_post',
    function ($orderId) {
        $Order   = QUI\ERP\Order\Handler::getInstance()->get($orderId);
        $Invoice = $Order->post();

        return $Invoice->getId();
    },
    ['orderId'],
    'Permission::checkAdminUser'
);
