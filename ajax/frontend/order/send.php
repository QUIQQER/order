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

        $result = $OrderProcess->create();
        $next   = false;

        if ($OrderProcess->getNextStep()) {
            $next = $OrderProcess->getNextStep()->getName();
        }

        return array(
            'html' => $result,
            'step' => $next
        );
    },
    array('orderId', 'current')
);
