<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_getOrderProcessUrl
 */

/**
 * Return the url of the order process
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_getOrderProcessUrl',
    function ($project) {
        return QUI\ERP\Order\Utils\Utils::getOrderProcessUrl(
            QUI::getProjectManager()->decode($project)
        );
    },
    ['project']
);
