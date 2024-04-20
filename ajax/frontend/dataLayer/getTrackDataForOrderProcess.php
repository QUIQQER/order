<?php

use QUI\ERP\Order\Utils\DataLayer;

QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_dataLayer_getTrackDataForOrderProcess',
    function ($orderHash) {
        try {
            $Orders = QUI\ERP\Order\Handler::getInstance();
            $Order = $Orders->getOrderByHash($orderHash);
            $Articles = $Order->getArticles();

            if (!$Articles->count()) {
                return [];
            }
        } catch (QUI\Exception) {
            return [];
        }

        return DataLayer::parseOrder($Order);
    },
    ['orderHash']
);
