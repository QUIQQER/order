<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_getBasket
 */

use QUI\ERP\Order\Factory as OrderFactory;
use QUI\ERP\Order\Handler as OrderHandler;

/**
 * Return the basket from the user
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_getBasket',
    function () {
        if (QUI::getUsers()->isNobodyUser(QUI::getUserBySession())) {
            return [];
        }

        try {
            $Basket = OrderHandler::getInstance()->getBasketFromUser(QUI::getUserBySession());
        } catch (QUI\Exception) {
            $Basket = OrderFactory::getInstance()->createBasket(QUI::getUserBySession());
        }

        return $Basket->toArray();
    },
    false
);
