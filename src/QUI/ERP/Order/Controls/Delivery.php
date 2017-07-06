<?php

/**
 * This file contains QUI\ERP\Order\Controls\Delivery
 */

namespace QUI\ERP\Order\Controls;

use QUI;
use QUI\ERP\Order\Handler;

/**
 * Class Delivery
 *
 * @package QUI\ERP\Order\Controls
 */
class Delivery extends AbstractOrderingStep
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

        return $Engine->fetch(dirname(__FILE__) . '/Delivery.html');
    }

    public function validate()
    {
        // TODO: Implement validate() method.
    }
}
