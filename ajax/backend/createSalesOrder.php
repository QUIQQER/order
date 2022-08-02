<?php

/**
 * Create a sales order from an order
 *
 * @param int $orderId
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_createSalesOrder',
    function (int $orderId) {
        // check if invoice is installed
        QUI::getPackage('quiqqer/salesorders');

        $Handler    = QUI\ERP\Order\Handler::getInstance();
        $Order      = $Handler->get($orderId);
        $SalesOrder = $Order->createSalesOrder();

        return $SalesOrder->getHash();
    },
    ['orderId'],
    'Permission::checkAdminUser'
);
