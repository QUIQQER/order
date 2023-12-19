<?php

use QUI\ERP\Order\Handler as OrderHandler;
use QUI\ERP\Order\Utils\DataLayer;
use QUI\ERP\Products\Handler\Products;

QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_dataLayer_getTrackData',
    function ($basketId, $products) {
        if (!QUI::getUserBySession()->getId()) {
            $Basket = new QUI\ERP\Order\Basket\BasketGuest();
            $Basket->import(json_decode($products, true));
        } else {
            try {
                $Basket = OrderHandler::getInstance()->getBasketById($basketId);
            } catch (QUI\Exception $Exception) {
                return [];
            }
        }

        $Locale = QUI::getLocale();
        $List = $Basket->getProducts();

        if (!$List) {
            return [];
        }


        $list = $List->toArray();
        $products = $list['products'];

        // generate result
        $items = [];

        foreach ($products as $product) {
            $Product = Products::getProduct($product['id']);
            $item = DataLayer::parseProduct($Product, $Locale);

            $items[] = $item;
        }

        return [
            'currency' => $List->getCurrency()->getCode(),
            'value' => $list['sum'],
            'items' => $items
        ];
    },
    [
        'basketId',
        'products'
    ]
);
