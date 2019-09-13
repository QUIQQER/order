<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_getNext
 */

/**
 * Return the next step
 *
 * @param integer $orderId
 * @param string $currentStep
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_getPrevious',
    function ($orderId, $current, $orderHash, $basketEditable) {
        if (!isset($basketEditable) || $basketEditable === '') {
            $basketEditable = true;
        }

        $OrderProcess = new QUI\ERP\Order\OrderProcess([
            'orderId'        => (int)$orderId,
            'orderHash'      => $orderHash,
            'step'           => $current,
            'basketEditable' => \boolval($basketEditable)
        ]);

        $Previous = $OrderProcess->getPreviousStep();

        if (!$Previous) {
            $Previous = $OrderProcess->getFirstStep();
        }

        $OrderProcess->setAttribute('step', $Previous->getName());

        return [
            'html' => $OrderProcess->create(),
            'step' => $Previous->getName(),
            'url'  => $OrderProcess->getStepUrl($Previous->getName()),
            'hash' => $OrderProcess->getStepHash()
        ];
    },
    ['orderId', 'current', 'orderHash', 'basketEditable']
);
