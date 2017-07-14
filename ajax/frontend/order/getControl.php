<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_getControl
 */

/**
 * Return the ordering control
 *
 * @param integer $orderId
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_getControl',
    function ($orderId) {
        $OrderProcess = new QUI\ERP\Order\Controls\Ordering(array(
            'orderId' => (int)$orderId
        ));

        $Output = new QUI\Output();
        $result = $OrderProcess->create();

        $css = QUI\Control\Manager::getCSS();


        return $Output->parse($css . $result);
    },
    array('orderId')
);
