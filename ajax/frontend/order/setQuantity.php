<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_getStep
 */

/**
 * Return the wanted step
 *
 * @param integer $orderId
 * @param string $current
 * @return array
 */

use QUI\ERP\Order\Basket\BasketOrder;

QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_setQuantity',
    function ($orderHash, $pos, $quantity) {
        try {
            QUI\ERP\Order\Handler::getInstance()->getOrderByHash($orderHash);
        } catch (QUI\Exception $Exception) {
            return;
        }

        $quantity = (int)$quantity;
        $pos      = (int)$pos - 1;

        $Basket = new BasketOrder($orderHash);
        $Products = $Basket->getProducts();
        $products = $Products->getProducts(); // get as array

        if (isset($products[$pos])) {
            $products[$pos]->setQuantity($quantity);
        }

        $Basket->toOrder();
    },
    ['orderHash', 'pos', 'quantity']
);
