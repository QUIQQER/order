<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_getBasket
 */

use QUI\ERP\Order\Handler as OrderHandler;

/**
 * Return the basket from the user
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_getBasket',
    function () {
        return OrderHandler::getInstance()->getBasketFromUser(
            QUI::getUserBySession()
        )->toArray();
    },
    false
);
