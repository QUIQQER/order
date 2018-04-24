<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_processingStatus_get
 */

use QUI\ERP\Order\ProcessingStatus\Handler;

/**
 * Create a new  processing status
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_processingStatus_get',
    function ($id) {
        return Handler::getInstance()->getProcessingStatus($id)->toArray();
    },
    ['id'],
    'Permission::checkAdminUser'
);
