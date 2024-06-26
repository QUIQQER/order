<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_list
 */

/**
 * Return the order list for a grid
 *
 * @param string $params - JSON query params
 *
 * @return array
 */

use QUI\ERP\Order\OrderInProcess;

QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_list',
    function () {
        $User = QUI::getUserBySession();
        $Orders = QUI\ERP\Order\Handler::getInstance();
        $orders = $Orders->getOrdersInProcessFromUser($User);

        return array_map(function ($Order) {
            /* @var $Order OrderInProcess */
            return $Order->toArray();
        }, $orders);
    },
    false
);
