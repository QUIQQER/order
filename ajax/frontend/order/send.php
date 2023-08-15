<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_send
 */

/**
 * Send the order
 *
 * @param integer $orderId
 * @param string $current
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_send',
    function ($current, $orderHash) {
        try {
            $_REQUEST['current'] = $current;
            $_REQUEST['payableToOrder'] = true;

            $OrderProcess = new QUI\ERP\Order\OrderProcess([
                'orderHash' => $orderHash,
                'step' => $current
            ]);

            $result = $OrderProcess->create();
            $current = false;

            if ($OrderProcess->getCurrentStep()) {
                $current = $OrderProcess->getCurrentStep()->getName();
            }

            return [
                'html' => $result,
                'step' => $current,
                'url' => $OrderProcess->getStepUrl($current),
                'hash' => $OrderProcess->getStepHash()
            ];
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.unknown.error')
            );
        }
    },
    ['current', 'orderHash']
);
