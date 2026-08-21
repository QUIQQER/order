<?php

use QUI\ERP\Order\Utils\DataLayer;

QUI::getAjax()->registerFunction(
    'package_quiqqer_order_ajax_frontend_dataLayer_getProductListData',
    function ($productIds, $startIndex) {
        $productIds = json_decode($productIds, true);

        if (!is_array($productIds)) {
            return [];
        }

        $validProductIds = [];

        foreach ($productIds as $productId) {
            if (!is_int($productId) && !is_string($productId)) {
                continue;
            }

            if (!ctype_digit((string)$productId)) {
                continue;
            }

            $validProductIds[] = (int)$productId;
        }

        return [
            'items' => DataLayer::parseProductItems(
                $validProductIds,
                QUI::getLocale(),
                max(0, (int)$startIndex)
            )
        ];
    },
    ['productIds', 'startIndex']
);
