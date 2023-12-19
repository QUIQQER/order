<?php

use QUI\ERP\Order\Utils\DataLayer;
use QUI\ERP\Products\Handler\Products;

QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_dataLayer_getProductData',
    function ($productId) {
        try {
            $productId = (int)$productId;
            $Product = Products::getProduct($productId);

            return DataLayer::parseProduct($Product);
        } catch (QUI\Exception $Exception) {
            return [];
        }
    },
    ['productId']
);
