<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_products_get
 */

/**
 * Return the product list
 *
 * @param integer $productIds - JSON Array
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_products_get',
    function ($productIds) {
        $Control = new QUI\ERP\Order\Controls\Products\ProductList([
            'productsIds' => json_decode($productIds, true)
        ]);

        return QUI\ControlUtils::parse($Control);
    },
    ['productIds']
);
