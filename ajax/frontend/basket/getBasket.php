<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_getBasket
 */

/**
 * Return the basket from the user
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_getBasket',
    function () {
        $User    = QUI::getUserBySession();
        $Handler = QUI\ERP\Order\Handler::getInstance();

        try {
            $Basket = $Handler->getBasketFromUser($User);
        } catch (QUI\Exception $Exception) {
            $Basket = QUI\ERP\Order\Factory::getInstance()->createBasket($User);
        }

        return $Basket->toArray();
    },
    false
);
