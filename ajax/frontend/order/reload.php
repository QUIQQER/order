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
    'package_quiqqer_order_ajax_frontend_order_reload',
    function ($orderId, $step, $orderHash, $basketEditable) {
        $_REQUEST['current'] = $step;

        $OrderProcess = new QUI\ERP\Order\OrderProcess([
            'orderId' => $orderId,
            'orderHash' => $orderHash,
            'basketEditable' => boolval($basketEditable)
        ]);

        $Order = $OrderProcess->getOrder();
        $Current = $OrderProcess->getCurrentStep();

        $OrderProcess->setAttribute('step', $Current->getName());
        $OrderProcess->setAttribute('orderHash', $Order->getUUID());

        $html = $OrderProcess->create();
        $current = $OrderProcess->getCurrentStep()->getName();

        return [
            'html' => $html,
            'step' => $current,
            'url' => $OrderProcess->getStepUrl($Current->getName()),
            'hash' => $Order->getUUID()
        ];
    },
    ['orderId', 'step', 'orderHash', 'basketEditable']
);
