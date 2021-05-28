<?php

/**
 * This file contains QUI\ERP\Order\Controls\OrderProcess\Checkout
 */

namespace QUI\ERP\Order\Controls\OrderProcess;

use QUI;

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
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSFile(\dirname(__FILE__).'/Checkout.css');
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

                if (!$this->getOrder()) {
                    return;
                }

                try {
                    $Order = $this->getOrder();

                    QUI::getSession()->set(
                        'termsAndConditions-'.$Order->getHash(),
                        0
                    );
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::writeDebugException($Exception);
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
        $Order  = $this->getOrder();

        $Order->recalculate();

        $ArticleList = $Order->getArticles();
        $Articles    = $ArticleList->toUniqueList();
        $Articles->hideHeader();

        $text = QUI::getLocale()->get(
            'quiqqer/order',
            'ordering.step.checkout.checkoutAcceptText',
            [
                'terms_and_conditions' => $this->getLinkOf('terms_and_conditions')
            ]
        );

        QUI::getEvents()->fireEvent(
            'quiqqerOrderOrderProcessCheckoutOutput',
            [$this, &$text]
        );

        // comment
        $comment = '';

        /*if (QUI::getSession()->get('comment-customer')) {
            $comment .= QUI::getSession()->get('comment-customer')."\n";
        }*/

        if (QUI::getSession()->get('comment-message')) {
            $comment .= QUI::getSession()->get('comment-message');
        }

        $comment = \trim($comment);

        $Engine->assign([
            'User'            => $Order->getCustomer(),
            'InvoiceAddress'  => $Order->getInvoiceAddress(),
            'DeliveryAddress' => $Order->getDeliveryAddress(),
            'Payment'         => $Order->getPayment(),
            'Shipping'        => $Order->getShipping(),
            'comment'         => $comment,
            'Articles'        => $Articles,
            'Order'           => $Order,
            'text'            => $text
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/Checkout.html');
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
        $Order   = $this->getOrder();
        $Payment = $Order->getPayment();

        if ($Order->isSuccessful()) {
            return;
        }

        if (!$Payment) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/order',
                'exception.order.payment.missing'
            ]);
        }

        if ($Order instanceof QUI\ERP\Order\Order) {
            return;
        }

        if (!QUI::getSession()->get('termsAndConditions-'.$Order->getHash())) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/order',
                'exception.order.termsAndConditions.missing'
            ]);
        }
    }

    /**
     * Order was ordered with costs
     *
     * @return void
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Exception
     */
    public function save()
    {
        if (isset($_REQUEST['termsAndConditions']) && !empty($_REQUEST['termsAndConditions'])) {
            $Order = $this->getOrder();

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
     * @throws QUI\Permissions\Exception
     * @throws QUI\Exception
     */
    public function forceSave()
    {
        $Order   = $this->getOrder();
        $Payment = $Order->getPayment();

        if (!$Payment || !$Order) {
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
    public function getLinkOf($config)
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
        $values = \json_decode($values, true);

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
