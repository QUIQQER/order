<?php

/**
 * This file contains QUI\ERP\Order\Controls\Payment
 */

namespace QUI\ERP\Order\Controls;

use QUI;
use QUI\ERP\Order\Handler;

/**
 * Class Payment
 *
 * @package QUI\ERP\Order\Controls
 */
class Payment extends AbstractOrderingStep
{
    /**
     * Payment constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = array())
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/Payment.css');
    }

    /**
     * @return string
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Orders = Handler::getInstance();
        $Order  = $Orders->getOrderInProcess($this->getAttribute('orderId'));

        $Customer = $Order->getCustomer();
        $User     = QUI::getUserBySession();

        $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();
        $payments = $Payments->getUserPayments($User);

        $Engine->assign(array(
            'User'     => $User,
            'Customer' => $Customer,
            'payments' => $payments
        ));

        return $Engine->fetch(dirname(__FILE__) . '/Payment.html');
    }


    public function validate()
    {
        // TODO: Implement validate() method.
    }
}
