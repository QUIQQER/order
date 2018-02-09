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

        // check if basket has an order
        // if an order exists, check if the order has already been placed
        $hash = $Basket->getHash();

        if (!empty($hash)) {
            try {
                $Order = QUI\ERP\Order\Handler::getInstance()->getOrderByHash($hash);

                if ($Order instanceof QUI\ERP\Order\Order) {
                    // $Basket->clear();
                    // $Basket->setHash('');
                    // $Basket->save();
                }
            } catch (QUI\Exception $Exception) {
            }
        }

        return $Basket->toArray();
    },
    false
);
