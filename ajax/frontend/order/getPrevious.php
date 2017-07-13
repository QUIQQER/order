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

        $OrderProcess = new QUI\ERP\Order\Controls\Ordering(array(
            'orderId' => (int)$orderId
        ));

        $Next = $OrderProcess->getPreviousStep();

        if (!$Next) {
            $Next = $OrderProcess->getFirstStep();
        }

        $OrderProcess->setAttribute('step', $Next->getName());

        return array(
            'html' => $OrderProcess->create(),
            'step' => $Next->getName()
        );
    },
    array('orderId', 'current')
);
