<?php

/**
 * This file contains QUI\ERP\Order\FrontendUsers\Controls\UserOrders
 */

namespace QUI\ERP\Order\FrontendUsers\Controls;

use QUI;
use QUI\ERP\Order\AbstractOrder;

/**
 * Class UserOrders
 *
 * @package QUI\ERP\Order\FrontendUSers\Controls
 */
class UserOpenedOrders extends UserOrders
{
    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $User   = QUI::getUserBySession();
        $Orders = QUI\ERP\Order\Handler::getInstance();

        $allOrders = $Orders->getOrdersByUser($User);
        $orders    = [];
        $hashes    = [];

        // filter not paid orders
        foreach ($allOrders as $Order) {
            /* @var $Order QUI\ERP\Order\Order */
            $hashes[] = $Order->getHash();

            if ($Order->isPosted()) {
                $Invoice = $Order->getInvoice();

                if ($Invoice->getAttribute('paid_status') === AbstractOrder::PAYMENT_STATUS_PAID) {
                    continue;
                }
            }

            if (!$Order->isPosted()) {
                if ($Order->getAttribute('paid_status') === AbstractOrder::PAYMENT_STATUS_PAID) {
                    continue;
                }
            }

            $Order->setAttribute(
                'downloadLink',
                URL_OPT_DIR.'quiqqer/order/bin/frontend/order.pdf.php?order='.$Order->getHash()
            );

            $orders[] = $Order;
            $hashes[] = $Order->getHash();
        }

        // orders in process
        $allOrdersInProcess = $Orders->getOrdersInProcessFromUser($User);
        $hashes             = array_flip($hashes);

        /* @var $OrderInProcess QUI\ERP\Order\OrderInProcess */
        foreach ($allOrdersInProcess as $OrderInProcess) {
            if (!isset($hashes[$OrderInProcess->getHash()])) {
                $orders[] = $OrderInProcess;
            }
        }

        $Engine->assign([
            'orders' => $orders,
            'this'   => $this
        ]);

        return $Engine->fetch(dirname(__FILE__).'/UserOrders.html');
    }
}
