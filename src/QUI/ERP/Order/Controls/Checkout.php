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

        $this->addCSSFile(dirname(__FILE__).'/Checkout.css');
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

        if ($Order->getDataEntry('orderedWithCosts') == 1) {
            $Payment = $Order->getPayment();
            $payment = $Order->getDataEntry('orderedWithCostsPayment');

            if ($payment == $Payment->getId() && $Payment->getPaymentType()->isGateway()) {
                $Engine->assign('Gateway', $Payment);
            }
        }

        $Engine->assign(array(
            'User'            => $Order->getCustomer(),
            'InvoiceAddress'  => $Order->getInvoiceAddress(),
            'DeliveryAddress' => $Order->getDeliveryAddress(),
            'Payment'         => $Order->getPayment(),
            'Articles'        => $Articles
        ));

        return $Engine->fetch(dirname(__FILE__).'/Checkout.html');
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

    /**
     * @throws QUI\ERP\Order\Exception
     */
    public function validate()
    {
        $Orders  = Handler::getInstance();
        $Order   = $Orders->getOrderInProcess($this->getAttribute('orderId'));
        $Payment = $Order->getPayment();

        if (!$Payment) {
            throw new QUI\ERP\Order\Exception(array(
                'quiqqer/order',
                'exception.order.payment.missing'
            ));
        }
    }

    /**
     * Order was ordered with costs
     */
    public function save()
    {
        $Orders  = Handler::getInstance();
        $Order   = $Orders->getOrderInProcess($this->getAttribute('orderId'));
        $Payment = $Order->getPayment();

        if (!$Payment) {
            return;
        }

        if (!isset($_REQUEST['current']) || $_REQUEST['current'] !== 'checkout') {
            return;
        }

        if (!isset($_REQUEST['payableToOrder'])) {
            return;
        }

        $Order->setData('orderedWithCosts', 1);
        $Order->setData('orderedWithCostsPayment', $Payment->getId());
        $Order->save();

//        wird über process provider gemacht
//        if (!$Payment->getPaymentType()->isGateway()) {
//            $Order->createOrder(QUI::getUsers()->getSystemUser());
//        }
    }
}
