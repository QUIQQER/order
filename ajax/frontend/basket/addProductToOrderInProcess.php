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
    'package_quiqqer_order_ajax_frontend_basket_addProductToOrderInProcess',
    function ($productId, $fields, $quantity) {
        $fields = json_decode($fields, true);
        $Order  = QUI\ERP\Order\Factory::getInstance()->createOrderInProcess();
        $Order->setData('basketConditionOrder', 2);

        if (!is_array($fields)) {
            $fields = [];
        }

        try {
            $Product = new QUI\ERP\Order\Basket\Product($productId, ['fields' => $fields]);
            $Real    = QUI\ERP\Products\Handler\Products::getProduct($productId); // check if active

            if (!$Real->isActive()) {
                return false;
            }

            if (!empty($quantity)) {
                $Product->setQuantity($quantity);
            }

            $Order->addArticle($Product->toArticle());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            // @todo message an benutzer - Product konnte nicht aufgenommen werden
        }

        $Order->save();

        return $Order->getHash();
    },
    ['productId', 'fields', 'quantity']
);
