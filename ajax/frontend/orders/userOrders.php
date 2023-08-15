<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_getControl
 */

/**
 * Return the ordering control
 *
 * @param integer $orderId
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_orders_userOrders',
    function ($page, $limit) {
        $Control = new QUI\ERP\Order\FrontendUsers\Controls\UserOrders([
            'page' => $page,
            'limit' => $limit
        ]);

        return QUI\ControlUtils::parse($Control);
    },
    ['page', 'limit']
);
