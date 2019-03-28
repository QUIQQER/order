<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_processingStatus_update
 */

use QUI\ERP\Order\ProcessingStatus\Handler;

/**
 * Update a processing status
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_processingStatus_update',
    function ($id, $color, $title) {
        Handler::getInstance()->updateProcessingStatus(
            $id,
            $color,
            \json_decode($title, true)
        );
    },
    ['id', 'color', 'title'],
    'Permission::checkAdminUser'
);
