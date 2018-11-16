<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_getBasket
 */

use QUI\ERP\Order\Handler as OrderHandler;
use QUI\ERP\Order\Factory as OrderFactory;

/**
 * Return the basket from the user
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_getBasket',
    function () {
        try {
            $Basket = OrderHandler::getInstance()->getBasketFromUser(QUI::getUserBySession());
        } catch (QUI\Exception $Exception) {
            $Basket = OrderFactory::getInstance()->createBasket(QUI::getUserBySession());
        }

        return $Basket->toArray();
    },
    false
);
