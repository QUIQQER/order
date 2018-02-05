<?php

/**
 * This file contains QUI\ERP\Order\Controls\OrderProcess\Delivery
 */

namespace QUI\ERP\Order\Controls\OrderProcess;

use QUI;
use QUI\ERP\Order\Handler;

/**
 * Class Delivery
 *
 * @package QUI\ERP\Order\Controls
 * @todo in delivery
 */
class Delivery extends QUI\ERP\Order\Controls\AbstractOrderingStep
{
    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Orders = Handler::getInstance();
        $Order  = $Orders->getOrderInProcess($this->getAttribute('orderId'));

        $Engine->assign(array(
            'User' => $Order->getCustomer()
        ));

        return $Engine->fetch(dirname(__FILE__).'/Delivery.html');
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getName($Locale = null)
    {
        return 'Delivery';
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return 'fa-truck';
    }

    public function validate()
    {
        // TODO: Implement validate() method.
    }


    public function save()
    {
        // TODO: Implement save() method.
    }
}
