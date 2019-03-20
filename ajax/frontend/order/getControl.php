<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_getControl
 */

/**
 * Return the order process control
 *
 * @param integer $orderId
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_getControl',
    function ($orderHash, $basket) {
        if (!isset($basket)) {
            $basket = true;
        }

        $OrderProcess = new QUI\ERP\Order\OrderProcess([
            'orderHash' => $orderHash,
            'basket'    => $basket
        ]);

        $Output = new QUI\Output();
        $result = $OrderProcess->create();
        $css    = QUI\Control\Manager::getCSS();

        return $Output->parse($css.$result);
    },
    ['orderHash', 'basket']
);
