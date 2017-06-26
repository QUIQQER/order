<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_processingStatus_create
 */

use QUI\ERP\Order\ProcessingStatus\Factory;

/**
 * Create a new  processing status
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_processingStatus_create',
    function ($id, $color, $title) {
        Factory::getInstance()->createProcessingStatus(
            $id,
            $color,
            json_decode($title, true)
        );
    },
    array('id', 'color', 'title'),
    'Permission::checkAdminUser'
);
