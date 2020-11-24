<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_save
 */

use QUI\ERP\Order\Handler;
use QUI\ERP\Order\Factory;
use QUI\System\Log;

/**
 * Saves the basket
 * and the temporary order if the user is not nobody
 *
 * @param integer $orderId
 * @param string $articles
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_save',
    function ($basketId, $products) {
        $User   = QUI::getUserBySession();
        $Basket = new QUI\ERP\Order\Basket\Basket($basketId, $User);

        if (!QUI::getUsers()->isNobodyUser($User)) {
            try {
                $Order = Handler::getInstance()->getLastOrderInProcessFromUser($User);
            } catch (QUI\Exception $Exception) {
                $Order = Factory::getInstance()->createOrderInProcess($User);

                Log::writeDebugException($Exception);
            }

            $BasketOrder = new QUI\ERP\Order\Basket\BasketOrder($Order->getHash(), $User);
            $BasketOrder->import(\json_decode($products, true));

            $productsCalc = $BasketOrder->getProducts()->toArray();
            $products     = $productsCalc['products'];

            // set the order products to the basket
            $Basket->import($products);
        } else {
            $Basket->import(\json_decode($products, true));
        }

        return $Basket->toArray();
    },
    ['basketId', 'products']
);
