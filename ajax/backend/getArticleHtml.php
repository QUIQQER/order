<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_get
 */

use QUI\ERP\Order\Handler;

/**
 * Returns an order
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_getArticleHtml',
    function ($orderId) {
        $Order    = Handler::getInstance()->get($orderId);
        $View     = $Order->getView();
        $Articles = $View->getArticles();

        return $Articles->render();
    },
    ['orderId'],
    'Permission::checkAdminUser'
);
