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
    function ($current, $orderHash) {
        $OrderProcess = new QUI\ERP\Order\OrderProcess([
            'orderHash' => $orderHash,
            'step'      => $current
        ]);

        $Next = $OrderProcess->getNextStep();

        if (!$Next) {
            $Next = $OrderProcess->getFirstStep();
        }

        $OrderProcess->setAttribute('step', $Next->getName());

        $html    = $OrderProcess->create();
        $Current = $OrderProcess->getCurrentStep();
        $current = $Current->getName();

        return [
            'html' => $html,
            'step' => $current,
            'url'  => $OrderProcess->getStepUrl($Current->getName()),
            'hash' => $OrderProcess->getStepHash()
        ];
    },
    ['current', 'orderHash']
);
