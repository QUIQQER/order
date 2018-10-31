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
    function ($basketId, $orderHash) {
        $Basket = new QUI\ERP\Order\Basket\Basket($basketId);
        $Basket->clear();
        $Basket->save();

        try {
            if ($orderHash) {
                $OrderBasket = new QUI\ERP\Order\Basket\BasketOrder($orderHash);
                $OrderBasket->clear();
                $OrderBasket->save();
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return $Basket->toArray();
    },
    ['basketId', 'orderHash']
);
