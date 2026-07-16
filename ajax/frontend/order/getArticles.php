<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_getArticles
 */

/**
 * Return the articles of an order
 *
 * @param integer $orderHash
 * @return array
 */
QUI::getAjax()->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_getArticles',
    function ($orderHash) {
        $OrderProcess = new QUI\ERP\Order\OrderProcess([
            'orderHash' => $orderHash
        ]);

        $Order = $OrderProcess->getOrder();

        if ($Order === null) {
            return [];
        }

        return $Order->getArticles()->toArray();
    },
    ['orderHash']
);
