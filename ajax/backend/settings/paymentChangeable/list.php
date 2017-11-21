<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_settings_paymentChangeable_list
 */

use QUI\ERP\Order\ProcessingStatus\Handler;
use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Accounting\Payments\Types\Payment;

/**
 * Returns processing list for a grid
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_settings_paymentChangeable_list',
    function () {
        $Config   = QUI::getPackage('quiqqer/order')->getConfig();
        $payments = Payments::getInstance()->getPayments();
        $section  = $Config->getSection('paymentChangeable');

        $result = array();

        /* @var $Payment Payment */
        foreach ($payments as $Payment) {
            $paymentId = $Payment->getId();

            if (!isset($section[$paymentId])) {
                $result[$paymentId] = 1;
                continue;
            }

            $result[$paymentId] = $section[$paymentId];
        }

        return $result;
    },
    false,
    'Permission::checkAdminUser'
);
