<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_calc
 */

/**
 * Calc the basket
 *
 * @param integer $orderId
 * @param string $articles
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_calc',
    function ($products) {
        $Basket = new QUI\ERP\Order\Basket\BasketGuest();
        $Basket->import(json_decode($products, true));

        return $Basket->toArray();
    },
    ['products']
);
