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
    function ($orderId, $current) {
        $_REQUEST['current'] = $current;

        $OrderProcess = new QUI\ERP\Order\OrderProcess(array(
            'orderId' => (int)$orderId
        ));

        $Previous = $OrderProcess->getPreviousStep();

        if (!$Previous) {
            $Previous = $OrderProcess->getFirstStep();
        }

        $OrderProcess->setAttribute('step', $Previous->getName());

        return array(
            'html' => $OrderProcess->create(),
            'step' => $Previous->getName()
        );
    },
    array('orderId', 'current')
);
