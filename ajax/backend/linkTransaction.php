<?php

use QUI\ERP\Accounting\Payments\Transactions\Handler as TransactionHandler;
use QUI\Utils\Security\Orthos;
use QUI\Exception;

/**
 * Assign a transaction to an order.
 *
 * @param string $orderHash
 * @param string $txId
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_linkTransaction',
    function ($orderHash, $txId) {
        $Orders = QUI\ERP\Order\Handler::getInstance();
        $Order = $Orders->getOrderByHash($orderHash);

        $Transaction = TransactionHandler::getInstance()->get(Orthos::clear($txId));

        if ($Transaction->isHashLinked($Order->getHash())) {
            throw new Exception([
                'quiqqer/order',
                'message.ajax.backend.linkTransaction.error.tx_already_linked',
                [
                    'orderHash' => $Order->getHash(),
                    'txId' => $Transaction->getTxId()
                ]
            ]);
        }

        $Order->linkTransaction($Transaction);
    },
    ['orderHash', 'txId'],
    'Permission::checkAdminUser'
);
