<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_addComment
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
    'package_quiqqer_order_ajax_backend_addComment',
    function ($orderId, $message) {
        $Order = QUI\ERP\Order\Handler::getInstance()->get($orderId);
        $Order->addComment($message);
        $Order->update();
    },
    ['orderId', 'message'],
    'Permission::checkAdminUser'
);
