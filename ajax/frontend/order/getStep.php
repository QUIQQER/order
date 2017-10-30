<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_getStep
 */

/**
 * Return the wanted step
 *
 * @param integer $orderId
 * @param string $current
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_getStep',
    function ($orderId, $step) {
        $_REQUEST['current'] = $step;

        $OrderProcess = new QUI\ERP\Order\OrderProcess(array(
            'orderId' => (int)$orderId
        ));

        $Current = $OrderProcess->getCurrentStep();

        if (!$Current) {
            $Current = $OrderProcess->getFirstStep();
        }

        $OrderProcess->setAttribute('step', $Current->getName());
        $html    = $OrderProcess->create();
        $current = $OrderProcess->getCurrentStep()->getName();

        return array(
            'html' => $html,
            'step' => $current
        );
    },
    array('orderId', 'step')
);
