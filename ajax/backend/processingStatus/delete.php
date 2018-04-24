<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_processingStatus_delete
 */

use QUI\ERP\Order\ProcessingStatus\Handler;

/**
 * Delete a processing status
 *
 * @param int $id
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_processingStatus_delete',
    function ($id) {
        Handler::getInstance()->deleteProcessingStatus($id);
    },
    ['id'],
    'Permission::checkAdminUser'
);
