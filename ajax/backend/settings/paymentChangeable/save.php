<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_settings_paymentChangeable_save
 */

use QUI\ERP\Order\ProcessingStatus\Handler;
use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Accounting\Payments\Types\Payment;

/**
 * Save the payment changeable status
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_settings_paymentChangeable_save',
    function ($data) {
        $data     = json_decode($data, true);
        $Config   = QUI::getPackage('quiqqer/order')->getConfig();
        $payments = Payments::getInstance()->getPayments();
        $section  = $Config->getSection('paymentChangeable');

        $result = [];

        /* @var $Payment Payment */
        foreach ($payments as $Payment) {
            $paymentId = $Payment->getId();

            if (isset($data[$paymentId])) {
                $result[$paymentId] = $data[$paymentId] ? 1 : 0;
                continue;
            }

            if (!isset($section[$paymentId])) {
                $result[$paymentId] = 1;
                continue;
            }

            $result[$paymentId] = $section[$paymentId];
        }

        $Config->setSection('paymentChangeable', $result);
        $Config->save();
    },
    ['data'],
    'Permission::checkAdminUser'
);
