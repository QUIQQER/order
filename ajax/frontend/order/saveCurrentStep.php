<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_saveCurrentStep
 */

/**
 * Return the wanted step
 *
 * @param integer $orderId
 * @param string $current
 * @param string $data - JSON data
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_saveCurrentStep',
    function ($step, $data, $orderHash) {
        $data = \json_decode($data, true);

        unset($_REQUEST['data']);

        foreach ($data as $key => $value) {
            $_REQUEST[$key] = $value;
        }

        $_REQUEST['current'] = $step;

        $OrderProcess = new QUI\ERP\Order\OrderProcess([
            'orderHash' => $orderHash
        ]);

        $Step = $OrderProcess->getCurrentStep();
        $Step->save();

        return [
            'hash' => $OrderProcess->getOrder()->getHash()
        ];
    },
    ['step', 'data', 'orderHash']
);
