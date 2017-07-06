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

        $steps = $this->getSteps();
        $step  = $this->getAttribute('step');

        if (!$step && isset($_REQUEST['step'])) {
            $step = $_REQUEST['step'];
            $this->setAttribute('step', $step);
        }

        if (!$step || !isset($steps[$step])) {
            reset($steps);
            $this->setAttribute('step', key($steps));
        }
    }

    /**
     * @return string
     */
    public function getBody()
    {
        $Engine  = QUI::getTemplateManager()->getEngine();
        $Current = $this->getCurrentStep();
        $next    = $this->getNextStepName();

        // @todo check all previous steps
        if ($Current->isValid() === false) {
            $next = false;
        }

        $Engine->assign(array(
            'this'        => $this,
            'CurrentStep' => $Current,
            'Site'        => $this->getSite(),
            'next'        => $next,
            'previous'    => $this->getPreviousStepName(),
            'steps'       => $this->getSteps()
        ));

        return $Engine->fetch(dirname(__FILE__) . '/Ordering.html');
    }

    /**
     * Return the current Step
     *
     * @return OrderingStepInterface
     */
    public function getCurrentStep()
    {
        $steps = $this->getSteps();

        return $steps[$this->getCurrentStepName()];
    }

    /**
     * Return the current step name / key
     *
     * @return string
     */
    protected function getCurrentStepName()
    {
        $step  = $this->getAttribute('step');
        $steps = $this->getSteps();

        if (isset($steps[$step])) {
            return $step;
        }

        reset($steps);
        return key($steps);
    }

    /**
     * Return the next step
     *
     * @return bool|string
     */
    protected function getNextStepName()
    {
        $step  = $this->getCurrentStepName();
        $steps = $this->getSteps();

        $keys = array_keys($steps);
        $pos  = array_search($step, $keys);
        $next = $pos + 1;

        if (isset($keys[$next])) {
            return $keys[$next];
        }

        return false;
    }

    /**
     * Return the previous step
     *
     * @return bool|string
     */
    protected function getPreviousStepName()
    {
        $step  = $this->getCurrentStepName();
        $steps = $this->getSteps();

        $keys = array_keys($steps);
        $pos  = array_search($step, $keys);
        $prev = $pos - 1;

        if (isset($keys[$prev])) {
            return $keys[$prev];
        }

        return false;
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
     * @return array
     *
     * @todo implement API
     */
    protected function getSteps()
    {
        $params = array(
            'orderId' => $this->getOrder()->getId()
        );

        return array(
            'basket'   => new Basket($params),
            'address'  => new Address($params),
            'delivery' => new Delivery($params),
            'payment'  => new Payment($params),
            'checkout' => new Checkout($params)
        );
    }
}
