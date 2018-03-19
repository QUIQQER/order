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
    function ($orderId, $step, $orderHash) {
        $_REQUEST['current'] = $step;

        $OrderProcess = new QUI\ERP\Order\OrderProcess([
            'orderId'   => (int)$orderId,
            'orderHash' => $orderHash
        ]);

        $Current = $OrderProcess->getCurrentStep();

        if (!$Current) {
            $Current = $OrderProcess->getFirstStep();
        }

        $OrderProcess->setAttribute('step', $Current->getName());
        $html    = $OrderProcess->create();
        $current = $OrderProcess->getCurrentStep()->getName();

        return [
            'html' => $html,
            'step' => $current,
            'url'  => $OrderProcess->getStepUrl($Current->getName()),
            'hash' => $OrderProcess->getStepHash()
        ];
    },
    ['orderId', 'step', 'orderHash']
);
