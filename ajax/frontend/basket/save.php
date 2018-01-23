<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_save
 */

/**
 * Saves the basket to the temporary order
 *
 * @param integer $orderId
 * @param string $articles
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_save',
    function ($basketId, $products) {
        $Basket = new \QUI\ERP\Order\Basket\Basket($basketId);
        $Basket->import(json_decode($products, true));
        $Basket->save();
    },
    array('basketId', 'products')
);
