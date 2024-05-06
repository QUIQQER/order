<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_payments_format
 */

/**
 * Format payments
 *
 * @param string|integer $payments - List of payments
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_payments_format',
    function ($payments) {
        $payments = json_decode($payments, true);
        $result = [];

        $Locale = QUI::getLocale();
        $Currency = QUI\ERP\Defaults::getCurrency();
        $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();

        foreach ($payments as $payment) {
            $paymentTitle = '';
            $txId = '';

            try {
                $Payment = $Payments->getPaymentType($payment['payment']);
                $paymentTitle = $Payment->getTitle();
            } catch (QUI\Exception) {
            }

            if (isset($payment['txid'])) {
                $txId = $payment['txid'];
            }

            $result[] = [
                'date' => $Locale->formatDate($payment['date']),
                'amount' => $Currency->format($payment['amount']),
                'payment' => $paymentTitle,
                'txid' => $txId
            ];
        }

        return $result;
    },
    ['payments'],
    'Permission::checkAdminUser'
);
