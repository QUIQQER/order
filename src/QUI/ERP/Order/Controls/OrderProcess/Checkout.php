<?php

/**
 * This file contains QUI\ERP\Order\Controls\OrderProcess\Checkout
 */

namespace QUI\ERP\Order\Controls\OrderProcess;

use QUI;
use QUI\ERP\Order\Handler;

/**
 * Class Address
 * - Tab / Panel for the address
 *
 * @package QUI\ERP\Order\Controls
 */
class Checkout extends QUI\ERP\Order\Controls\AbstractOrderingStep
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
     * Overwrite setAttribute then checkout can react to onGetBody
     *
     * @param string $name
     * @param array|bool|object|string $val
     * @return QUI\QDOM
     */
    public function setAttribute($name, $val)
    {
        if ($name === 'Process') {
            /* @var $Process QUI\ERP\Order\OrderProcess */
            $Process = $val;
            $self    = $this;

            // reset the session termsAndConditions if the step is not the checkout step
            $Process->Events->addEvent('onGetBody', function () use ($Process, $self) {
                if ($Process->getAttribute('step') === $self->getName()) {
                    return;
                }

                try {
                    $Orders = Handler::getInstance();
                    $Order  = $Orders->getOrderInProcess($this->getAttribute('orderId'));

                    QUI::getSession()->set(
                        'termsAndConditions-'.$Order->getHash(),
                        0
                    );
                } catch (QUI\Exception $Exception) {
                }
            });
        }

        return parent::setAttribute($name, $val);
    }

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

        $Articles = $Order->getArticles()->toUniqueList();
        $Articles->hideHeader();

        if (QUI::getSession()->get('termsAndConditions-'.$Order->getHash())
            && $Order->getDataEntry('orderedWithCosts') == 1) {
            $Payment = $Order->getPayment();
            $payment = $Order->getDataEntry('orderedWithCostsPayment');

            if ($payment == $Payment->getId() && $Payment->getPaymentType()->isGateway()) {
                $Engine->assign('Gateway', $Payment->getPaymentType());
                $Engine->assign('gatewayDisplay', $Payment->getPaymentType()->getGatewayDisplay($Order));
            }
        }


        $Engine->assign(array(
            'User'            => $Order->getCustomer(),
            'InvoiceAddress'  => $Order->getInvoiceAddress(),
            'DeliveryAddress' => $Order->getDeliveryAddress(),
            'Payment'         => $Order->getPayment(),
            'Articles'        => $Articles,

            'text' => QUI::getLocale()->get(
                'quiqqer/order',
                'ordering.step.checkout.checkoutAcceptText',
                array(
                    'terms_and_conditions' => $this->getLinkOf('terms_and_conditions'),
                    'revocation'           => $this->getLinkOf('revocation')
                )
            )
        ));

        return $Engine->fetch(dirname(__FILE__).'/Checkout.html');
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getName($Locale = null)
    {
        return 'Checkout';
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

        if (!QUI::getSession()->get('termsAndConditions-'.$Order->getHash())) {
            throw new QUI\ERP\Order\Exception(array(
                'quiqqer/order',
                'exception.order.termsAndConditions.missing'
            ));
        }
    }

    /**
     * Order was ordered with costs
     *
     * @return void
     *
     * @throws QUI\Exception
     */
    public function save()
    {
        if (isset($_REQUEST['termsAndConditions']) && !empty($_REQUEST['termsAndConditions'])) {
            $Orders = Handler::getInstance();
            $Order  = $Orders->getOrderInProcess($this->getAttribute('orderId'));

            QUI::getSession()->set(
                'termsAndConditions-'.$Order->getHash(),
                (int)$_REQUEST['termsAndConditions']
            );
        }

        if (!isset($_REQUEST['current']) || $_REQUEST['current'] !== $this->getName()) {
            return;
        }

        if (!isset($_REQUEST['payableToOrder'])) {
            return;
        }

        $this->forceSave();
    }

    /**
     * Save order as start order payment
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     */
    public function forceSave()
    {
        $Orders  = Handler::getInstance();
        $Order   = $Orders->getOrderInProcess($this->getAttribute('orderId'));
        $Payment = $Order->getPayment();

        if (!$Payment) {
            return;
        }

        $Order->setData('orderedWithCosts', 1);
        $Order->setData('orderedWithCostsPayment', $Payment->getId());
        $Order->save();
    }

    /**
     * Return a link from a specific site type
     *
     * @param string $config
     * @return string
     */
    protected function getLinkOf($config)
    {
        try {
            $Config  = QUI::getPackage('quiqqer/erp')->getConfig();
            $values  = $Config->get('sites', $config);
            $Project = $this->getProject();
        } catch (QUI\Exception $Exception) {
            return '';
        }

        if (!$values) {
            return '';
        }

        $lang   = $Project->getLang();
        $values = json_decode($values, true);

        if (!isset($values[$lang]) || empty($values[$lang])) {
            return '';
        }

        try {
            $Site = QUI\Projects\Site\Utils::getSiteByLink($values[$lang]);

            $url   = $Site->getUrlRewritten();
            $title = $Site->getAttribute('title');

            $project = $Site->getProject()->getName();
            $lang    = $Site->getProject()->getLang();
            $id      = $Site->getId();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return '';
        }

        return '<a href="'.$url.'" target="_blank" 
            data-project="'.$project.'" 
            data-lang="'.$lang.'" 
            data-id="'.$id.'">'.$title.'</a>';
    }
}
