<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_create
 */

/**
 * Returns order list for a grid
 *
 * @return integer
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_create',
    function () {
        return QUI\ERP\Order\Factory::create()->getId();
    },
    false,
    'Permission::checkAdminUser'
);
