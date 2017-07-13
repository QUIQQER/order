<?php

/**
 * This file contains QUI\ERP\Order\Controls\OrderingProcess
 */

namespace QUI\ERP\Order\Controls;

use function DusanKasan\Knapsack\first;
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

        if (!$step && isset($_REQUEST['current'])) {
            $step = $_REQUEST['current'];
            $this->setAttribute('step', $step);
        }

        if (!$step && isset($_REQUEST['current'])) {
            $step = $_REQUEST['current'];
            $this->setAttribute('step', $step);
        }


        if (!$step || !isset($steps[$step])) {
            reset($steps);
            $this->setAttribute('step', key($steps));
        }
    }

    /**
     * Checks the submit status
     * Must the previous step be saved?
     */
    protected function checkSubmission()
    {
        if (!isset($_REQUEST['current'])) {
            return;
        }

        $preStep = $_REQUEST['current'];
        $PreStep = $this->getNextStepByName($preStep);

        if (!$PreStep) {
            return;
        }

        try {
            $PreStep->save();
        } catch (QUI\Exception $Exception) {
        }
    }

    /**
     *
     */
    protected function toOrder()
    {
        $steps = $this->getSteps();

        // check all previous steps
        // is one is invalid, go to them
        foreach ($steps as $name => $Step) {
            if ($Step->getName() === 'checkout'
                || $Step->getName() === 'finish'
            ) {
                continue;
            }

            $Step->validate();
        }

        // all is correct, we can do the payment process
        $Payment = $this->getOrder()->getPayment();
    }

    /**
     * @return string
     */
    public function getBody()
    {
        if (isset($_REQUEST['payableToOrder'])) {
            try {
                $this->toOrder();
            } catch (QUI\Exception $Exception) {
            }
        }

        $Engine  = QUI::getTemplateManager()->getEngine();
        $Current = $this->getCurrentStep();
        $steps   = $this->getSteps();

        $this->checkSubmission();

        // check all previous steps
        // is one is invalid, go to them
        foreach ($steps as $name => $Step) {
            if ($name === $Current->getName()) {
                break;
            }

            /* @var $Step AbstractOrderingStep */
            if ($Step->isValid() === false) {
                $Current = $Step;
                break;
            }
        }

        $error    = false;
        $next     = $this->getNextStepName($Current);
        $previous = $this->getPreviousStepName();

        $payableToOrder = false;


        if ($Current->showNext() === false) {
            $next = false;
        }

        if ($previous === ''
            || $Current->getName() === $this->getFirstStep()->getName()
        ) {
            $previous = false;
        }

        if ($Current->getName() === 'checkout') {
            $next           = false;
            $payableToOrder = true;
        }

        try {
            $Current->validate();
        } catch (QUI\ERP\Order\Exception $Exception) {
            $error = $Exception->getMessage();
        }

        $Engine->assign(array(
            'this'           => $this,
            'error'          => $error,
            'CurrentStep'    => $Current,
            'Site'           => $this->getSite(),
            'next'           => $next,
            'previous'       => $previous,
            'payableToOrder' => $payableToOrder,
            'steps'          => $this->getSteps()
        ));

        return $Engine->fetch(dirname(__FILE__) . '/Ordering.html');
    }

    /**
     * Return the current Step
     *
     * @return AbstractOrderingStep
     */
    public function getCurrentStep()
    {
        $steps   = $this->getSteps();
        $Current = $steps[$this->getCurrentStepName()];

        return $Current;
    }

    /**
     * Return the first step
     *
     * @return AbstractOrderingStep
     */
    public function getFirstStep()
    {
        return array_values($this->getSteps())[0];
    }

    /**
     * Return the next step
     *
     * @param string $name - Name of the step
     * @return bool|AbstractOrderingStep
     */
    protected function getNextStepByName($name)
    {
        $steps = $this->getSteps();

        if (isset($steps[$name])) {
            return $steps[$name];
        }

        return false;
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

        return $this->getFirstStep()->getName();
    }

    /**
     * Return the next step
     *
     * @param null|AbstractOrderingStep $StartStep
     * @return bool|string
     */
    protected function getNextStepName($StartStep = null)
    {
        if ($StartStep === null) {
            $step = $this->getCurrentStepName();
        } else {
            $step = $StartStep->getName();
        }

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

        $sites = $Project->getSitesIds(array(
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
            'orderId' => $this->getOrder()->getId(),
            'Order'   => $this->getOrder()
        );

        $Basket   = new Basket($params);
        $Address  = new Address($params);
        $Delivery = new Delivery($params);
        $Payment  = new Payment($params);
        $Checkout = new Checkout($params);
        $Finish   = new Finish($params);

        return array(
            $Basket->getName()   => $Basket,
            $Address->getName()  => $Address,
            $Delivery->getName() => $Delivery,
            $Payment->getName()  => $Payment,
            $Checkout->getName() => $Checkout,
            $Finish->getName()   => $Finish
        );
    }
}
