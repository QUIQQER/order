<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_removePos
 */

/**
 *
 * @param integer $basketId
 * @param integer $productId
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_removePos',
    function ($basketId, $pos) {
        $User   = QUI::getUserBySession();
        $Basket = new QUI\ERP\Order\Basket\Basket($basketId, $User);

        QUI::getEvents()->fireEvent(
            'quiqqerOrderBasketRemovePos',
            [$Basket, $pos]
        );
    },
    ['basketId', 'pos']
);
