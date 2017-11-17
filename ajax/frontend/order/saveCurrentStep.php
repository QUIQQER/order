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
    function ($orderId, $step, $data) {
        $data = json_decode($data, true);

        unset($_REQUEST['data']);

        foreach ($data as $key => $value) {
            $_REQUEST[$key] = $value;
        }

        $_REQUEST['current'] = $step;

        $Ordering = new QUI\ERP\Order\OrderProcess(array(
            'orderId' => (int)$orderId
        ));

        $Step = $Ordering->getCurrentStep();
        $Step->save();
    },
    array('orderId', 'step', 'data')
);