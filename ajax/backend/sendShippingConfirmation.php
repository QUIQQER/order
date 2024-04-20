<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_sendShippingConfirmation
 */

/**
 * Send a shipping confirmation to the customer
 *
 * @param string|integer $orderId - ID of the order
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_sendShippingConfirmation',
    function ($orderId) {
        $Orders = QUI\ERP\Order\Handler::getInstance();

        try {
            $Order = $Orders->get($orderId);
        } catch (QUI\Exception) {
            $Order = $Orders->getOrderByHash($orderId);
        }

        $Order->sendShippingConfirmation();
    },
    ['orderId'],
    'Permission::checkAdminUser'
);
