<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_addProductsToBasketOrder
 */

/**
 * Add products to the order
 *
 * @param integer $orderId
 * @param string $articles
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_addProductsToBasketOrder',
    function ($orderHash, $products) {
        try {
            $OrderBasket = new QUI\ERP\Order\Basket\BasketOrder($orderHash);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            // @todo message an benutzer - Product konnte nicht aufgenommen werden
            return;
        }

        $products = \json_decode($products, true);

        foreach ($products as $product) {
            if (!isset($product['id'])) {
                continue;
            }

            $productId = $product['id'];
            $fields    = [];
            $quantity  = 1;

            if (isset($product['fields']) && \is_array($fields)) {
                $fields = [];
            }

            if (isset($product['quantity'])) {
                $quantity = (int)$product['quantity'];
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
        }
    },
    ['orderHash', 'products']
);
