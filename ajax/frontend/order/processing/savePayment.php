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
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_processing_savePayment',
    function ($orderHash, $payment) {
        $Processing   = new QUI\ERP\Order\Controls\OrderProcess\Processing();
        $OrderProcess = new QUI\ERP\Order\OrderProcess([
            'orderHash' => $orderHash,
            'step'      => $Processing->getName()
        ]);

        /* @var $Processing \QUI\ERP\Order\Controls\OrderProcess\Processing */
        $Processing = $OrderProcess->getCurrentStep();
        $Order      = $OrderProcess->getOrder();

        $Processing->setAttribute('Order', $Order);
        $Processing->savePayment($payment);
    },
    ['orderHash', 'payment']
);
