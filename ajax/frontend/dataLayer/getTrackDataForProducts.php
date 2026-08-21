<?php

use QUI\ERP\Order\Basket\BasketGuest;
use QUI\ERP\Order\Utils\DataLayer;

QUI::getAjax()->registerFunction(
    'package_quiqqer_order_ajax_frontend_dataLayer_getTrackDataForProducts',
    function ($products) {
        $products = json_decode($products, true);

        if (!is_array($products)) {
            return [];
        }

        $Basket = new BasketGuest();
        $Basket->import($products);

        return DataLayer::parseProductList(
            $Basket->getProducts(),
            QUI::getLocale()
        );
    },
    ['products']
);
