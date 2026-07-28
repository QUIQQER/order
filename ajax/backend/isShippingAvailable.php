<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_isShippingAvailable
 */

/**
 * Checks if the Shipping classes used by the Order administration are
 * actually available through the runtime autoloader.
 */
QUI::getAjax()->registerFunction(
    'package_quiqqer_order_ajax_backend_isShippingAvailable',
    function (): bool {
        return class_exists('QUI\ERP\Shipping\Shipping')
            && class_exists('QUI\ERP\Shipping\ShippingStatus\Handler')
            && class_exists('QUI\ERP\Shipping\Tracking\Tracking');
    },
    false,
    'Permission::checkAdminUser'
);
