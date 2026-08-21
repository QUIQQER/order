<?php

use QUI\ERP\Order\Handler as OrderHandler;
use QUI\ERP\Order\Utils\DataLayer;

QUI::getAjax()->registerFunction(
    'package_quiqqer_order_ajax_frontend_dataLayer_getTrackData',
    function ($basketId, $products) {
        if (!QUI::getUserBySession()->getUUID()) {
            $Basket = new QUI\ERP\Order\Basket\BasketGuest();
            $Basket->import(json_decode($products, true));
        } else {
            try {
                $Basket = OrderHandler::getInstance()->getBasketById($basketId);
            } catch (QUI\Exception) {
                return [];
            }
        }

        return DataLayer::parseProductList(
            $Basket->getProducts(),
            QUI::getLocale()
        );
    },
    [
        'basketId',
        'products'
    ]
);
