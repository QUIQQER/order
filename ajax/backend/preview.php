<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_preview
 */

/**
 * Preview of an order
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_preview',
    function ($orderId, $onlyArticles) {
        try {
            $Order = QUI\ERP\Order\Handler::getInstance()->get($orderId);
        } catch (QUI\Exception $exception) {
            $Order = QUI\ERP\Order\Handler::getInstance()->getOrderByHash($orderId);
        }

        $View = $Order->getView();

        if (!isset($onlyArticles)) {
            $onlyArticles = false;
        }

        $onlyArticles = (int)$onlyArticles;

        if ($onlyArticles) {
            return $View->previewOnlyArticles();
        }

        return $View->previewHTML();
    },
    ['orderId', 'onlyArticles'],
    'Permission::checkAdminUser'
);
