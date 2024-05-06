<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_processingStatus_get
 */

use QUI\ERP\Order\Handler as Orders;
use QUI\ERP\Order\ProcessingStatus\Handler;

/**
 * Get status change notification text for a specific order
 *
 * @param int $id - ProcessingStatus ID
 * @param int $orderId - Order ID
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_processingStatus_getNotificationText',
    function ($id, $orderId) {
        try {
            $Order = Orders::getInstance()->get($orderId);

            return Handler::getInstance()->getProcessingStatus($id)->getStatusChangeNotificationText($Order);
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return '';
        }
    },
    ['id', 'orderId'],
    'Permission::checkAdminUser'
);
