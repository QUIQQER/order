<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_getNext
 */

/**
 * Return the next step
 *
 * @param integer $orderId
 * @param string $current
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_getNext',
    function ($orderId, $current) {
        $_REQUEST['current'] = $current;

        $OrderProcess = new QUI\ERP\Order\OrderProcess(array(
            'orderId' => (int)$orderId
        ));

        $Next = $OrderProcess->getNextStep();

        if (!$Next) {
            $Next = $OrderProcess->getFirstStep();
        }

        $OrderProcess->setAttribute('step', $Next->getName());

        $html    = $OrderProcess->create();
        $current = $OrderProcess->getCurrentStep()->getName();

        return array(
            'html' => $html,
            'step' => $current
        );
    },
    array('orderId', 'current')
);