<?php

/**
 * This file contains QUI\ERP\Order\Controls\Address
 */

namespace QUI\ERP\Order\Controls;

use QUI;
use QUI\ERP\Order\Handler;

/**
 * Class Address
 * - Tab / Panel for the address
 *
 * @package QUI\ERP\Order\Controls
 */
class Address extends QUI\Control
{
    /**
     * @return string
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Orders = Handler::getInstance();
        $Order  = $Orders->getOrderInProcess($this->getAttribute('orderId'));

        $Engine->assign(array(
            'User' => $Order->getCustomer()
        ));

        return $Engine->fetch(dirname(__FILE__) . '/Address.html');
    }
}
