<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_hasBasketEquivalent
 */

/**
 * Return the articles of an order
 *
 * @param integer $orderHash
 * @return array
 */

use QUI\ERP\Order\Handler as OrderHandler;
use QUI\ERP\Products\Utils\Products as ProductUtils;
use QUI\ERP\Products\Field\Types\BasketConditions;

QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_hasBasketEquivalent',
    function ($orderHash) {
        try {
            $Order = QUI\ERP\Order\Handler::getInstance()->getOrderByHash($orderHash);
        } catch (QUI\Exception $Exception) {
            return true;
        }

        $Basket = OrderHandler::getInstance()->getBasketFromUser(QUI::getUserBySession());

        if ($Basket->count() !== $Order->count()) {
            return false;
        }

        $products = $Basket->getProducts()->getProducts();
        $articles = $Order->getArticles()->toArray();
        $articles = $articles['articles'];

        $isInArticles = function ($productId) use ($articles) {
            foreach ($articles as $article) {
                if ($article['id'] === $productId) {
                    return true;
                }
            }

            return false;
        };

        /* @var $Product \QUI\ERP\Order\Basket\Product */
        foreach ($products as $Product) {
            $condition = ProductUtils::getBasketCondition($Product);

            switch ($condition) {
                case BasketConditions::TYPE_2:
                case BasketConditions::TYPE_6:
                    return false;
            }

            if ($isInArticles($Product->getId()) === false) {
                return false;
            }
        }

        return true;
    },
    ['orderHash']
);
