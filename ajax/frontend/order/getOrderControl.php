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
    'package_quiqqer_order_ajax_frontend_order_getOrderControl',
    function ($orderHash) {
        $OrderProcess = new QUI\ERP\Order\Controls\Order\Order([
            'orderHash' => $orderHash
        ]);

        $Output = new QUI\Output();
        $result = $OrderProcess->create();
        $css = QUI\Control\Manager::getCSS();
        $View = null;

        if (method_exists($OrderProcess->getOrder(), 'getView')) {
            $View = $OrderProcess->getOrder()->getView();
        }

        return [
            'html' => $Output->parse($css . $result),
            'data' => $View?->toArray()
        ];
    },
    ['orderHash']
);
