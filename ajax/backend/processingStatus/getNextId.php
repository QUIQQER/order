<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_processingStatus_getNextId
 */

use QUI\ERP\Order\ProcessingStatus\Factory;

/**
 * Return next available ID
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_processingStatus_getNextId',
    function () {
        return Factory::getInstance()->getNextId();
    },
    false,
    'Permission::checkAdminUser'
);
