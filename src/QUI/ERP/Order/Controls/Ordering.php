<?php

/**
 * This file contains QUI\ERP\Order\Controls\OrderingProcess
 */

namespace QUI\ERP\Order\Controls;

use QUI;

/**
 * Class OrderingProcess
 * Coordinates the order process, (basket -> address -> delivery -> payment -> invoice)
 *
 * @package QUI\ERP\Order\Basket
 */
class Ordering extends QUI\Control
{
    /**
     * @var QUI\ERP\Order\OrderProcess
     */
    protected $Order = null;

    /**
     * Basket constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = array())
    {
        parent::__construct($attributes);

        $this->setAttributes(array(
            'Site'     => false,
            'data-qui' => 'package/quiqqer/order/bin/frontend/controls/Ordering',
            'orderId'  => false
        ));

        $this->addCSSFile(dirname(__FILE__) . '/Ordering.css');
    }

    /**
     * @return string
     */
    public function getBody()
    {
        $Engine  = QUI::getTemplateManager()->getEngine();
        $Current = $this->getCurrentStep();

        switch (get_class($Current)) {
            case Basket::class:
                $next     = 'address';
                $previous = false;

                /* @var $Current Basket */
                if (!$Current->getBasket()->getArticles()->count()) {
                    $next = false;
                }
                break;

            default:
                $next     = 'address';
                $previous = false;
        }

        $Engine->assign(array(
            'this'        => $this,
            'CurrentStep' => $this->getCurrentStep(),
            'Site'        => $this->getSite(),
            'next'        => $next,
            'previous'    => $previous
        ));

        return $Engine->fetch(dirname(__FILE__) . '/Ordering.html');
    }

    /**
     * Return the current Step
     *
     * @return QUI\Control
     */
    public function getCurrentStep()
    {
        return $this->getSteps()[0];
    }

    /**
     * Return the order site
     *
     * @return QUI\Projects\Site
     */
    public function getSite()
    {
        if ($this->getAttribute('Site')) {
            return $this->getAttribute('Site');
        }

        $Project = QUI::getRewrite()->getProject();
        $sites   = $Project->getSitesIds(array(
            'where' => array(
                'type'   => 'quiqqer/order:types/orderingProcess',
                'active' => 1
            ),
            'limit' => 1
        ));

        if (isset($sites[0])) {
            $Site = $Project->get($sites[0]['id']);

            $this->setAttribute('Site', $Site);
            return $Site;
        }

        return $Project->firstChild();
    }

    /**
     * @return QUI\ERP\Order\OrderProcess
     */
    public function getOrder()
    {
        $orderId = $this->getAttribute('orderId');
        $Orders  = QUI\ERP\Order\Handler::getInstance();
        $User    = QUI::getUserBySession();

        try {
            if ($orderId !== false) {
                $Order = $Orders->getOrderInProcess($orderId);

                if ($Order->getCustomer()->getId() == $User->getId()) {
                    $this->Order = $Order;
                }
            }
        } catch (QUI\Erp\Order\Exception $Exception) {
        }

        if ($this->Order === null) {
            try {
                // select the last order in processing
                $this->Order = $Orders->getLastOrderInProcessFromUser($User);
            } catch (QUI\Erp\Order\Exception $Exception) {
                // if no order exists, we create one
                $this->Order = QUI\ERP\Order\Factory::getInstance()->createOrderProcess();
            }
        }

        return $this->Order;
    }

    /**
     *
     */
    protected function getProcess()
    {

    }

    /**
     * @return array
     */
    protected function getSteps()
    {
        $params = array(
            'orderId' => $this->getOrder()->getId()
        );

        return array(
            new Basket($params),
            new Address($params)
        );
    }
}
