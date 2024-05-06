<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_update
 */

use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\PriceFactors\Factor;
use QUI\ERP\Accounting\PriceFactors\FactorList;
use QUI\ERP\Order\ProcessingStatus\Handler;
use QUI\ERP\Shipping\Shipping;

/**
 * Update an order
 *
 * @param string|integer $orderId - ID of the order
 * @param string $data - JSON query data
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_setStatus',
    function ($orderId, $status) {
        $Order = QUI\ERP\Order\Handler::getInstance()->get($orderId);

        $Order->setProcessingStatus($status);
        $Order->update();

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/order',
                'message.backend.update.status',
                ['orderId' => $Order->getPrefixedNumber()]
            )
        );

        return QUI\ERP\Order\Handler::getInstance()->getOrderByHash($Order->getUUID())->toArray();
    },
    ['orderId', 'status'],
    'Permission::checkAdminUser'
);
