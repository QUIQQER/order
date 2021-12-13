<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_panel_getCategories
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_panel_getCategories',
    function () {
        try {
            return QUI\ERP\Order\Utils\Panel::getPanelCategories();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return [];
        }
    },
    false,
    'Permission::checkAdminUser'
);
