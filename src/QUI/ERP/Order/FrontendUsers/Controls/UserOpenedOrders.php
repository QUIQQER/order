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
        $Engine    = QUI::getTemplateManager()->getEngine();
        $User      = QUI::getUserBySession();
        $allOrders = QUI\ERP\Order\Handler::getInstance()->getOrdersByUser($User);
        $orders    = [];

        // filter not paid orders
        foreach ($allOrders as $Order) {
            /* @var $Order QUI\ERP\Order\Order */
            $Order->setAttribute(
                'downloadLink',
                URL_OPT_DIR.'quiqqer/order/bin/frontend/order.pdf.php?order='.$Order->getHash()
            );

            if (!$Order->isPosted()) {
                $orders[] = $Order;
                continue;
            }

            $Invoice = $Order->getInvoice();

            if ($Invoice->getAttribute('paid') !== AbstractOrder::PAYMENT_STATUS_PAID) {
                $orders[] = $Order;
            }
        }

        $Engine->assign([
            'orders' => $orders,
            'this'   => $this
        ]);

        return $Engine->fetch(dirname(__FILE__).'/UserOrders.html');
    }
}
