<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_getLastOrder
 */

/**
 * Return the last order from the user
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_getLastOrder',
    function () {
        $User   = QUI::getUserBySession();
        $Orders = QUI\ERP\Order\Handler::getInstance();

        if (QUI::getUsers()->isNobodyUser($User)) {
            return [];
        }

        try {
            $Order = $Orders->getLastOrderInProcessFromUser($User);
        } catch (QUI\Exception $Exception) {
            $Order = QUI\ERP\Order\Factory::getInstance()->createOrderInProcess();

            // merge with current basket
            try {
                $Basket = $Orders->getBasketFromUser($User);
                $Basket->toOrder($Order);
            } catch (QUI\Exception $Exception) {
            }
        }

        return $Order->toArray();
    },
    false
);
