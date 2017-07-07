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
class Address extends AbstractOrderingStep
{

    /**
     * Address constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = array())
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/Address.css');
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
        $Address  = $Customer->getAddress();

        /* @var $User QUI\Users\User */
        $User        = QUI::getUserBySession();
        $UserAddress = null;
        $addresses   = array();

        try {
            $UserAddress = $User->getStandardAddress();
        } catch (QUI\Exception $Exception) {
        }

        try {
            $addresses = $User->getAddressList();
        } catch (QUI\Exception $Exception) {
        }

        $Engine->assign(array(
            'User'        => $User,
            'UserAddress' => $UserAddress,
            'addresses'   => $addresses,

            'Customer' => $Customer,
            'Address'  => $Address
        ));

        return $Engine->fetch(dirname(__FILE__) . '/Address.html');
    }

    /**
     *
     */
    public function validate()
    {
        $User = QUI::getUserBySession();

    }
}
