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
    function ($orderId, $articles) {
        $Basket = new QUI\ERP\Order\Basket\Basket($orderId);
        $Basket->import(json_decode($articles, true));
        $Basket->save();
    },
    array('orderId', 'articles')
);
