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
    'package_quiqqer_order_ajax_frontend_basket_controls_basket',
    function ($basketId) {
        $User   = QUI::getUserBySession();
        $Basket = new QUI\ERP\Order\Basket\Basket($basketId, $User);

        $Control = new QUI\ERP\Order\Controls\Basket\Basket();
        $Control->setBasket($Basket);

        return $Control->create();
    },
    ['basketId']
);
