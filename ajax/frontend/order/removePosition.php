<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_removePosition
 */

/**
 * Return the wanted step
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_removePosition',
    function ($orderHash, $pos) {
        $OrderBasket = new QUI\ERP\Order\Basket\BasketOrder($orderHash);
        $OrderBasket->removePosition((int)$pos);

        return $OrderBasket->toArray();
    },
    ['orderHash', 'pos']
);
