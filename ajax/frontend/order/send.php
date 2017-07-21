<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_send
 */

/**
 * Send the order
 *
 * @param integer $orderId
 * @param string $current
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_send',
    function ($orderId, $current) {
        $_REQUEST['current']        = $current;
        $_REQUEST['payableToOrder'] = true;

        $OrderProcess = new QUI\ERP\Order\OrderProcess(array(
            'orderId' => (int)$orderId
        ));

        $Next = $OrderProcess->getNextStep();

        if (!$Next) {
            $Next = $OrderProcess->getFirstStep();
        }

        $OrderProcess->setAttribute('step', $Next->getName());

        return array(
            'html' => $OrderProcess->create(),
            'step' => $current
        );
    },
    array('orderId', 'current')
);
