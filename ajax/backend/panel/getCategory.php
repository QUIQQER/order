<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_panel_getCategory
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_panel_getCategory',
    function ($category) {
        try {
            return QUI\ERP\Order\Utils\Panel::getPanelCategory($category);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return [];
        }
    },
    ['category'],
    'Permission::checkAdminUser'
);
