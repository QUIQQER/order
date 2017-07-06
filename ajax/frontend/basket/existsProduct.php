<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_existsArticle
 */

use QUI\ERP\Products\Handler\Products;

/**
 * Is the product still active and available?
 *
 * @param integer $productId
 * @return bool
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_existsProduct',
    function ($productId) {
        try {
            $Product = Products::getProduct($productId);
            $Product->getView();

            return true;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return false;
    },
    array('productId')
);
