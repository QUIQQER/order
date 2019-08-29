<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_addPayment
 */

use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Accounting\Payments\Transactions\Factory as TransactionFactory;

/**
 * Add a payment to an order
 *
 * @param string|integer invoiceId - ID of the invoice
 * @param string|int $amount - amount of the payment
 * @param string $paymentMethod - Payment method
 * @param string|int $date - Date of the payment
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_addPayment',
    function ($orderId, $amount, $paymentMethod, $date) {
        $Orders  = QUI\ERP\Order\Handler::getInstance();
        $Payment = Payments::getInstance()->getPayment($paymentMethod);

        try {
            $Order = $Orders->get($orderId);
        } catch (QUI\Exception $Exception) {
            $Order = $Orders->getOrderByHash($orderId);
        }

        // create the transaction
        TransactionFactory::createPaymentTransaction(
            $amount,
            QUI\ERP\Defaults::getCurrency(),
            $Order->getHash(),
            $Payment->getPaymentType()->getName(),
            [],
            QUI::getUserBySession(),
            $date,
            $Order->getHash()
        );
    },
    ['orderId', 'amount', 'paymentMethod', 'date'],
    'Permission::checkAdminUser'
);
