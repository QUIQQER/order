<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_existsArticle
 */

/**
 * Is the product still active and available?
 *
 * @param integer $productId
 * @return bool
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_controls_small',
    function ($basketId, $orderHash) {
        $Basket = null;

        try {
            if ($orderHash) {
                $Basket = new QUI\ERP\Order\Basket\BasketOrder($orderHash);
            }
        } catch (QUI\Exception $Exception) {
        }

        if ($Basket === null) {
            $Basket = QUI\ERP\Order\Handler::getInstance()->getBasket($basketId);
        }

        $Control = new QUI\ERP\Order\Controls\Basket\Small();
        $Control->setBasket($Basket);

        return $Control->create();
    },
    ['basketId', 'orderHash']
);
