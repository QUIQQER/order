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
    'package_quiqqer_order_ajax_frontend_basket_controls_smallGuest',
    function ($products) {
        $products = \json_decode($products, true);
        $Basket = new QUI\ERP\Order\Basket\BasketGuest();
        $Basket->import($products);

        $Control = new QUI\ERP\Order\Controls\Basket\Small();
        $Control->setBasket($Basket);

        return $Control->create();
    },
    ['products']
);
