<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_save
 */

use QUI\ERP\Order\Handler;
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
        $User = QUI::getUserBySession();

        $Basket = new QUI\ERP\Order\Basket\Basket($basketId, $User);
        $Basket->import(\json_decode($products, true));

        if (!QUI::getUsers()->isNobodyUser($User)) {
            try {
                $Order       = Handler::getInstance()->getLastOrderInProcessFromUser($User);
                $BasketOrder = new QUI\ERP\Order\Basket\BasketOrder($Order->getHash(), $User);
                $BasketOrder->import(\json_decode($products, true));
            } catch (QUI\Exception $Exception) {
                Log::writeDebugException($Exception);
            }
        }

        return $Basket->toArray();
    },
    ['basketId', 'products']
);
