<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_addProductToBasketOrder
 */

/**
 * Add a product to the order
 *
 * @param integer $orderId
 * @param string $articles
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_addProductToBasketOrder',
    function ($orderHash, $productId, $fields, $quantity) {
        try {
            $OrderBasket = new QUI\ERP\Order\Basket\BasketOrder($orderHash);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            // @todo message an benutzer - Product konnte nicht aufgenommen werden
            return;
        }

        $fields = \json_decode($fields, true);

        if (!\is_array($fields)) {
            $fields = [];
        }

        try {
            $Product = new QUI\ERP\Order\Basket\Product($productId, ['fields' => $fields]);
            $Real    = QUI\ERP\Products\Handler\Products::getProduct($productId); // check if active

            if (!$Real->isActive()) {
                return;
            }

            if (!empty($quantity)) {
                $Product->setQuantity($quantity);
            }

            $OrderBasket->addProduct($Product);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            // @todo message an benutzer - Product konnte nicht aufgenommen werden
        }
    },
    ['orderHash', 'productId', 'fields', 'quantity']
);
