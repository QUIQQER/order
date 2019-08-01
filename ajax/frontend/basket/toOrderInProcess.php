<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_toOrderInProcess
 */

/**
 * Saves the basket to the temporary order
 *
 * @param integer $basketId
 * @param string $orderHash
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_toOrderInProcess',
    function ($basketId, $orderHash) {
        $User = QUI::getUserBySession();

        if (QUI::getUsers()->isNobodyUser($User)) {
            return;
        }

        $Basket       = new QUI\ERP\Order\Basket\Basket($basketId, $User);
        $OrderHandler = QUI\ERP\Order\Handler::getInstance();
        $Order        = null;

        if (!empty($orderHash)) {
            try {
                $Order = QUI\ERP\Order\Handler::getInstance()->getOrderByHash($orderHash);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        if ($Order === null) {
            try {
                $Order = $OrderHandler->getLastOrderInProcessFromUser($User);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        if ($Order) {
            $Basket->toOrder($Order);
        }
    },
    ['basketId', 'orderHash']
);
