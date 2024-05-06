<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_createInvoice
 */

/**
 * Create the invoice for the order
 *
 * @return integer
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_createInvoice',
    function ($orderId) {
        // check if invoice is installed
        QUI::getPackage('quiqqer/invoice');

        QUI\ERP\Order\Settings::getInstance()->set('order', 'autoInvoicePost', 0);
        QUI\ERP\Order\Settings::getInstance()->forceCreateInvoiceOn();

        $Handler = QUI\ERP\Order\Handler::getInstance();
        $Order = $Handler->get($orderId);
        $Invoice = $Order->createInvoice();

        QUI\ERP\Order\Settings::getInstance()->forceCreateInvoiceOff();

        return $Invoice->getUUID();
    },
    ['orderId'],
    'Permission::checkAdminUser'
);
