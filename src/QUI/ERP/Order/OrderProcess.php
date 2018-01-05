<?php

/**
 * This file contains QUI\ERP\Order\OrderProcess
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\ERP\Order\Controls;
use QUI\ERP\Order\Controls\AbstractOrderingStep;
use QUI\ERP\Order\Utils\OrderProcessSteps;

/**
 * Class OrderingProcess
 * Coordinates the order process, (basket -> address -> delivery -> payment -> invoice)
 *
 * @package QUI\ERP\Order\Basket
 */
class OrderProcess extends QUI\Control
{
    /**
     * @var QUI\ERP\Order\OrderInProcess
     */
    protected $Order = null;

    /**
     * @var null|AbstractOrderProcessProvider
     */
    protected $ProcessingProvider = null;

    /**
     * @return string
     */
    public static function getUrl()
    {
        return URL_DIR.'Bestellungen/';
    }

    /**
     * Basket constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = array())
    {
        parent::__construct($attributes);

        $this->setAttributes(array(
            'Site'      => false,
            'data-qui'  => 'package/quiqqer/order/bin/frontend/controls/OrderProcess',
            'orderHash' => false
        ));

        $this->addCSSFile(dirname(__FILE__).'/Controls/OrderProcess.css');

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
        $PreStep = $this->getStepByName($preStep);

        if (!$PreStep) {
            return;
        }

        try {
            $PreStep->save();
        } catch (QUI\Exception $Exception) {
        }
    }

    /**
     * Send the order
     */
    protected function send()
    {
        $steps     = $this->getSteps();
        $providers = QUI\ERP\Order\Handler::getInstance()->getOrderProcessProvider();

        // check all previous steps
        // is one is invalid, go to them
        foreach ($steps as $name => $Step) {
            /* @var $Step AbstractOrderingStep */
            if ($Step->getName() === 'checkout' || $Step->getName() === 'finish') {
                continue;
            }

            try {
                $Step->validate();
            } catch (Exception $Exception) {
                $this->setAttribute('current', $Step->getName());
            }
        }

        QUI::getEvents()->fireEvent('orderStart', [$this]);

        // Gehe die verschiedenen Processing Provider durch
        $Order   = $this->getOrder();
        $success = array();

        foreach ($providers as $Provider) {
            /* @var $Provider AbstractOrderProcessProvider */
            $status = $Provider->onOrderStart($Order);

            if ($status === AbstractOrderProcessProvider::PROCESSING_STATUS_PROCESSING) {
                $this->ProcessingProvider = $Provider;

                return;
            }

            if ($status === AbstractOrderProcessProvider::PROCESSING_STATUS_ABORT) {
                $Provider->onOrderAbort($Order);
                continue;
            }

            $success[] = $Provider->onOrderSuccess($Order);
        }

        // all runs fine

        // @todo Vorgehen bei gescheiterter Zahlung -> only at failedPaymentProcedure = execute
        $Order->createOrder();
        $Order->delete();

        $this->setAttribute('current', 'finish');
        $this->setAttribute('step', 'finish');
    }

    /**
     * Execute the payable step
     *
     * @return bool|string
     */
    protected function executePayableStatus()
    {
        if (!isset($_REQUEST['payableToOrder'])) {
            return false;
        }

        $template = dirname(__FILE__).'/Controls/OrderProcess.html';
        $Engine   = QUI::getTemplateManager()->getEngine();

        try {
            $this->send();

            // processing step
            // eq: payment gateway
            if ($this->ProcessingProvider !== null) {
                $this->ProcessingProvider;

                $ProcessingStep = new Controls\ProcessingProviderStep();
                $ProcessingStep->setProcessingProvider($this->ProcessingProvider);
                $ProcessingStep->setAttribute('Order', $this->getOrder());

                $Engine->assign(array(
                    'listWidth'      => floor(100 / count($this->getSteps())),
                    'this'           => $this,
                    'error'          => false,
                    'next'           => false,
                    'previous'       => 'checkout',
                    'payableToOrder' => false,
                    'steps'          => $this->getSteps(),
                    'CurrentStep'    => $ProcessingStep,
                    'Site'           => $this->getSite(),
                    'Order'          => $this->getOrder()
                ));

                return $Engine->fetch($template);
            }

            $Engine->assign(array(
                'listWidth'      => floor(100 / count($this->getSteps())),
                'this'           => $this,
                'error'          => false,
                'next'           => false,
                'previous'       => false,
                'payableToOrder' => false,
                'steps'          => $this->getSteps(),
                'CurrentStep'    => $this->getCurrentStep(),
                'Site'           => $this->getSite(),
                'Order'          => $this->getOrder()
            ));

            return $Engine->fetch($template);
        } catch (QUI\Exception $Exception) {
            if (DEBUG_MODE) {
                QUI\System\Log::writeException($Exception);
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        $template = dirname(__FILE__).'/Controls/OrderProcess.html';
        $Engine   = QUI::getTemplateManager()->getEngine();

        // payable step
        if (isset($_REQUEST['payableToOrder'])) {
            $result = $this->executePayableStatus();

            if ($result !== false) {
                return $result;
            }
        }

        // standard procedure
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

        $this->setAttribute('step', $Current->getName());

        $Engine->assign(array(
            'listWidth'      => floor(100 / count($this->getSteps())),
            'this'           => $this,
            'error'          => $error,
            'next'           => $next,
            'previous'       => $previous,
            'payableToOrder' => $payableToOrder,
            'steps'          => $this->getSteps(),
            'CurrentStep'    => $Current,
            'Site'           => $this->getSite(),
            'Order'          => $this->getOrder()
        ));

        return $Engine->fetch($template);
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
     * @param null|AbstractOrderingStep $StartStep
     * @return bool|AbstractOrderingStep
     */
    public function getNextStep($StartStep = null)
    {
        if ($StartStep === null) {
            $step = $this->getCurrentStepName();
        } else {
            $step = $StartStep->getName();
        }

        // @todo prüfen ob bezahlung schon da ist oder gemacht wurde
        // wenn es order id gibt und bezahlung, dann abschluss anzeigen

        $steps = $this->getSteps();

        $keys = array_keys($steps);
        $pos  = array_search($step, $keys);
        $next = $pos + 1;

        if (!isset($keys[$next])) {
            return false;
        }

        $key = $keys[$next];

        if (isset($steps[$key])) {
            return $steps[$key];
        }

        return false;
    }

    /**
     * Return the previous step
     *
     * @param null|AbstractOrderingStep $StartStep
     * @return bool|AbstractOrderingStep
     */
    public function getPreviousStep($StartStep = null)
    {
        if ($StartStep === null) {
            $step = $this->getCurrentStepName();
        } else {
            $step = $StartStep->getName();
        }

        $steps = $this->getSteps();

        $keys = array_keys($steps);
        $pos  = array_search($step, $keys);
        $prev = $pos - 1;

        if (!isset($keys[$prev])) {
            return false;
        }

        $key = $keys[$prev];

        if (isset($steps[$key])) {
            return $steps[$key];
        }

        return false;
    }

    /**
     * Return the step via its name
     *
     * @param string $name - Name of the step
     * @return bool|AbstractOrderingStep
     */
    protected function getStepByName($name)
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
        $Next = $this->getNextStep($StartStep);

        if ($Next) {
            return $Next->getName();
        }

        return false;
    }

    /**
     * Return the previous step
     *
     * @param null|AbstractOrderingStep $StartStep
     * @return bool|string
     */
    protected function getPreviousStepName($StartStep = null)
    {
        $Prev = $this->getPreviousStep($StartStep);

        if ($Prev) {
            return $Prev->getName();
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
                'type'   => 'quiqqer / order:types / orderingProcess',
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
     * @return QUI\ERP\Order\OrderInProcess
     */
    public function getOrder()
    {
        $orderHash = $this->getAttribute('orderHash');
        $Orders    = QUI\ERP\Order\Handler::getInstance();
        $User      = QUI::getUserBySession();

        try {
            if ($orderHash !== false) {
                $Order = $Orders->getOrderByHash($orderHash);

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
     * Return all steps
     *
     * @return array
     */
    protected function getSteps()
    {
        $Steps     = new OrderProcessSteps();
        $providers = QUI\ERP\Order\Handler::getInstance()->getOrderProcessProvider();

        $Basket = new Controls\Basket(array(
            'orderId'  => $this->getOrder()->getId(),
            'Order'    => $this->getOrder(),
            'priority' => 1
        ));

//        $Delivery = new Controls\Delivery($params);

        $Checkout = new Controls\Checkout(array(
            'orderId'  => $this->getOrder()->getId(),
            'Order'    => $this->getOrder(),
            'priority' => 4
        ));

        $Finish = new Controls\Finish(array(
            'orderId'  => $this->getOrder()->getId(),
            'Order'    => $this->getOrder(),
            'priority' => 5
        ));


        // init steps
        $Steps->append($Basket);

        /* @var $Provider QUI\ERP\Order\AbstractOrderProcessProvider */
        foreach ($providers as $Provider) {
            $Provider->initSteps($Steps, $this);
        }

        $Steps->append($Checkout);
        $Steps->append($Finish);

        $Steps->sort(function ($Step1, $Step2) {
            /* @var $Step1 QUI\ERP\Order\Controls\AbstractOrderingStep */
            /* @var $Step2 QUI\ERP\Order\Controls\AbstractOrderingStep */
            $p1 = $Step1->getAttribute('priority');
            $p2 = $Step2->getAttribute('priority');

            if ($p1 == $p2) {
                return 0;
            }

            return ($p1 < $p2) ? -1 : 1;
        });

        $result = array();

        foreach ($Steps as $Step) {
            $result[$Step->getName()] = $Step;
        }

        return $result;
    }
}
