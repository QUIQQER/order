<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_clear
 */

/**
 * Clears the basket
 *
 * @param string $basketId - ID of the basket
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_clear',
    function ($basketId) {
        $Basket = new QUI\ERP\Order\Basket\Basket($basketId);
        $Basket->clear();
        $Basket->save();

        return $Basket->toArray();
    },
    ['basketId']
);
