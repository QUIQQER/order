<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_getHistory
 */

/**
 * Returns the combined invoice history
 *
 * @param string $invoiceId - ID of the invoice or invoice hash
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_getHistory',
    function ($orderId) {
        $Orders = QUI\ERP\Order\Handler::getInstance();

        try {
            $Order = $Orders->get($orderId);
        } catch (QUI\Exception $Exception) {
            $Order = $Orders->getOrderByHash($orderId);
        }

        /* @var $Order \QUI\ERP\Order\Order */
        QUI\ERP\Accounting\Calc::calculatePayments($Order);

        $History = $Order->getHistory();
        $history = \array_map(function ($history) {
            $history['type'] = 'history';

            return $history;
        }, $History->toArray());

        $Comments = $Order->getComments();
        $comments = \array_map(function ($comment) {
            $comment['type'] = 'comment';

            return $comment;
        }, $Comments->toArray());

        $history = \array_merge($history, $comments);

        // transactions
        $Transactions = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();
        $transactions = $Transactions->getTransactionsByHash($Order->getHash());

        foreach ($transactions as $Tx) {
            /* @var $Tx \QUI\ERP\Accounting\Payments\Transactions\Transaction */
            $history[] = [
                'message' => $Tx->parseToText(),
                'time'    => \strtotime($Tx->getDate()),
                'type'    => 'transaction',
            ];
        }

        // sort
        \usort($history, function ($a, $b) {
            if ($a['time'] == $b['time']) {
                return 0;
            }

            return ($a['time'] < $b['time']) ? -1 : 1;
        });

        return $history;
    },
    ['orderId'],
    'Permission::checkAdminUser'
);
