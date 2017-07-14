<?php

/**
 * This file contains QUI\ERP\Order\Controls\Checkout
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
class Checkout extends AbstractOrderingStep
{
    /**
     * @var QUI\ERP\Order\Basket\Basket
     */
    protected $Basket;

    /**
     * Basket constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = array())
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/Checkout.css');
    }

    /**
     * @return string
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Orders = Handler::getInstance();
        $Order  = $Orders->getOrderInProcess($this->getAttribute('orderId'));

        $Articles = $Order->getArticles()->toUniqueList();
        $Articles->hideHeader();

        $Engine->assign(array(
            'User'            => $Order->getCustomer(),
            'InvoiceAddress'  => $Order->getInvoiceAddress(),
            'DeliveryAddress' => $Order->getDeliveryAddress(),
            'Payment'         => $Order->getPayment(),
            'Articles'        => $Articles
        ));

        return $Engine->fetch(dirname(__FILE__) . '/Checkout.html');
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'checkout';
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return 'fa-shopping-cart';
    }


    public function validate()
    {
        // TODO: Implement validate() method.
    }

    public function save()
    {
    }
}
