<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_processing_savePayment
 */

/**
 * save the payment
 *
 * @param integer $orderId
 * @return string
 */

use QUI\ERP\Order\Controls\OrderProcess\Processing;

QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_processing_savePayment',
    function ($orderHash, $payment) {
        $Processing = new Processing();
        $OrderProcess = new QUI\ERP\Order\OrderProcess([
            'orderHash' => $orderHash,
            'step' => $Processing->getName()
        ]);

        $Processing = $OrderProcess->getCurrentStep();
        $Order = $OrderProcess->getOrder();
        $Processing->setAttribute('Order', $Order);

        if (method_exists($Processing, 'savePayment')) {
            $Processing->savePayment($payment);
        }
    },
    ['orderHash', 'payment']
);
