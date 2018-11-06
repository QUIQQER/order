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
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_clear',
    function ($orderHash) {
        $OrderProcess = new QUI\ERP\Order\OrderProcess([
            'orderHash' => $orderHash
        ]);

        $Order = $OrderProcess->getOrder();
        $Order->clear();
    },
    ['orderHash']
);
