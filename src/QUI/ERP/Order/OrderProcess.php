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
 *
 * This is the ordering process
 * Coordinates the order process, (basket -> address -> delivery -> payment -> invoice)
 *
 * @package QUI\ERP\Order\Basket
 *
 * @event getBody [this]
 * @event getBodyBegin [this]
 * @event onSend [this]
 * @event onSendBegin [this]
 */
class OrderProcess extends QUI\Control
{
    /**
     * @var QUI\ERP\Order\OrderInProcess
     */
    protected $Order;

    /**
     * @var Basket\Basket
     */
    protected $Basket = null;

    /**
     * @var null|AbstractOrderProcessProvider
     */
    protected $ProcessingProvider = null;

    /**
     * List of order process steps
     *
     * @var array
     */
    protected $steps = [];

    /**
     * @var QUI\Events\Event
     */
    public $Events;

    /**
     * Basket constructor.
     *
     * @param array $attributes
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws Basket\Exception
     */
    public function __construct($attributes = [])
    {
        $this->setAttributes([
            'Site'      => false,
            'data-qui'  => 'package/quiqqer/order/bin/frontend/controls/OrderProcess',
            'orderHash' => false,
            'basket'    => true // import basket articles to the order, use the basket
        ]);

        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__).'/Controls/OrderProcess.css');

        $this->Events = new QUI\Events\Event();

        $User     = QUI::getUserBySession();
        $isNobody = QUI::getUsers()->isNobodyUser($User);

        // @todo guest ordering
        if ($isNobody) {
            header('Location: '.$this->getProject()->firstChild()->getUrlRewritten(), false, 301);
            exit;
        }


        // current step
        $steps = $this->getSteps();
        $step  = $this->getAttribute('step');
        $Order = $this->getOrder();

        if ($Order->getCustomer()->getId() !== $User->getId()) {
            throw new QUI\Permissions\Exception([
                'quiqqer/order',
                'exception.no.permission.for.this.order'
            ]);
        }

        // basket into the order
        $Basket = $this->getBasket();

        if (!$this->getAttribute('orderHash') && $this->getAttribute('basket')) {
            $Basket->toOrder($Order);
        }

        $this->setAttribute('basketId', $Basket->getId());
        $this->setAttribute('orderHash', $Order->getHash());

        // order is successfull, so no other step must be shown
        if ($Order && $Order->isSuccessful()) {
            $LastStep = end($steps);

            $this->setAttribute('step', $LastStep->getName());
            $this->setAttribute('orderHash', $Order->getHash());

            return;
        }

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

        // consider processing step
        // processing step is ok
        $Processing = $this->getProcessingStep();

        if ($Processing->getName() === $step) {
            $this->setAttribute('orderHash', $Order->getHash());

            return;
        }

        if (!$step || !isset($steps[$step])) {
            reset($steps);
            $this->setAttribute('step', key($steps));
        }
    }

    /**
     * Checks the submit status
     * Must the previous step be saved?
     *
     * In this case, it is the step the user took when he clicked next.
     * Or the user clicked a submit button in the step
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws Basket\Exception
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
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    /**
     * Check if the success full status is ok
     */
    protected function checkSuccessfulStatus()
    {
        try {
            $Current = $this->getCurrentStep();
        } catch (QUI\Exception $Exception) {
            return;
        }

        if (!($Current instanceof Controls\OrderProcess\Finish)
            && !($Current instanceof Controls\OrderProcess\Processing)) {
            return;
        }

        try {
            $Payment = $this->getOrder()->getPayment();

            if ($Payment && $Payment->isSuccessful($this->getOrder()->getHash())) {
                $this->getOrder()->setSuccessfulStatus();
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    /**
     * Send the order
     *
     * @throws QUI\Exception
     */
    protected function send()
    {
        $this->Events->fireEvent('sendBegin', [$this]);

        $steps     = $this->getSteps();
        $providers = QUI\ERP\Order\Handler::getInstance()->getOrderProcessProvider();

        // check all previous steps
        // is one invalid, go to them
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
        $OrderInProcess = $this->getOrder();
        $success        = [];

        $failedPaymentProcedure = Settings::getInstance()->get('order', 'failedPaymentProcedure');

        foreach ($providers as $Provider) {
            /* @var $Provider AbstractOrderProcessProvider */
            $status = $Provider->onOrderStart($OrderInProcess);

            if ($status === AbstractOrderProcessProvider::PROCESSING_STATUS_PROCESSING) {
                $this->ProcessingProvider = $Provider;

                if ($failedPaymentProcedure !== 'execute') {
                    return;
                }
            }

            if ($status === AbstractOrderProcessProvider::PROCESSING_STATUS_ABORT) {
                $Provider->onOrderAbort($OrderInProcess);
                continue;
            }

            $success[] = $Provider->onOrderSuccess($OrderInProcess);
        }

        // all runs fine
        if ($OrderInProcess instanceof OrderInProcess) {
            $Order = $OrderInProcess->createOrder();
            $OrderInProcess->delete();

            $this->Order = $Order;
        } else {
            $this->Order = $OrderInProcess;
        }

        $this->setAttribute('orderHash', $this->Order->getHash());

        $this->setAttribute('current', 'finish');
        $this->setAttribute('step', 'finish');

        // set all to successful
        $this->cleanup();

        $this->Events->fireEvent('send', [$this]);
    }

    /**
     * Cleanup stuff, look if smth is not needed anymore
     */
    protected function cleanup()
    {
        // set all to successful
        if (!$this->Order->isSuccessful()) {
            return;
        }

        // if temp order exist, and a normal order kill it
        try {
            $ProcessOrder = Handler::getInstance()->getOrderInProcessByHash(
                $this->Order->getHash()
            );

            $Order = Handler::getInstance()->getOrderByHash(
                $ProcessOrder->getHash()
            );

            if ($Order instanceof Order) {
                $ProcessOrder->delete();
            }

            $this->Order = $Order;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        if (method_exists($this->Basket, 'successful')) {
            $this->Basket->successful();
            $this->Basket->save();
        } else {
            try {
                $Basket = Handler::getInstance()->getBasketByHash($this->Order->getHash());
                $Basket->successful();
                $Basket->save();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }
    }

    /**
     * Execute the payable step
     *
     * @return bool|string
     *
     * @throws QUI\Exception
     */
    protected function executePayableStatus()
    {
        $template = dirname(__FILE__).'/Controls/OrderProcess.html';
        $Engine   = QUI::getTemplateManager()->getEngine();

        try {
            $this->send();

            // processing step
            // eq: payment gateway
            if ($this->ProcessingProvider !== null) {
                $ProcessingStep = $this->getProcessingStep();
                $ProcessingStep->setProcessingProvider($this->ProcessingProvider);
                $ProcessingStep->setAttribute('Order', $this->getOrder());

                $this->setAttribute('step', $ProcessingStep->getName());

                // shows payment changing, if allowed
                $changePayment = false;
                $Payment       = $this->getOrder()->getPayment();

                if (Settings::getInstance()->get('paymentChangeable', $Payment->getId())) {
                    $changePayment = true;
                }

                $Engine->assign([
                    'listWidth'          => floor(100 / count($this->getSteps())),
                    'this'               => $this,
                    'error'              => false,
                    'next'               => false,
                    'previous'           => false,
                    'payableToOrder'     => false,
                    'changePayment'      => $changePayment,
                    'steps'              => $this->getSteps(),
                    'CurrentStep'        => $ProcessingStep,
                    'currentStepContent' => QUI\ControlUtils::parse($ProcessingStep),
                    'Site'               => $this->getSite(),
                    'Order'              => $this->getOrder(),
                    'hash'               => $this->getStepHash()
                ]);

                return QUI\Output::getInstance()->parse($Engine->fetch($template));
            }

            $Engine->assign([
                'listWidth'          => floor(100 / count($this->getSteps())),
                'this'               => $this,
                'error'              => false,
                'next'               => false,
                'previous'           => false,
                'payableToOrder'     => false,
                'steps'              => $this->getSteps(),
                'CurrentStep'        => $this->getCurrentStep(),
                'currentStepContent' => QUI\ControlUtils::parse($this->getCurrentStep()),
                'Site'               => $this->getSite(),
                'Order'              => $this->getOrder(),
                'hash'               => $this->getStepHash()
            ]);

            return QUI\Output::getInstance()->parse($Engine->fetch($template));
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return false;
    }

    /**
     * Return the html of the order process
     *
     * @return string
     *
     * @throws QUi\Exception
     */
    public function getBody()
    {
        $this->Events->fireEvent('getBodyBegin', [$this]);

        $template = dirname(__FILE__).'/Controls/OrderProcess.html';
        $Engine   = QUI::getTemplateManager()->getEngine();

        // check if processing step is needed
        $processing = $this->checkProcessing();

        if (!empty($processing)) {
            return $processing;
        }

        // standard procedure
        $steps   = $this->getSteps();
        $Current = $this->getCurrentStep();

        $this->checkSubmission();
        $this->checkSuccessfulStatus();

        // check if order is finished
        $Order = $this->getOrder();

        if ($Order && $Order->isSuccessful()) {
            if ($Order instanceof OrderInProcess && !$Order->getOrderId()) {
                $Order->createOrder();
            }

            return $this->renderFinish();
        }

        // check all previous steps
        // is one invalid, go to them
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

        /* @var $Checkout AbstractOrderingStep */
        $Checkout = current(array_filter($this->getSteps(), function ($Step) {
            /* @var $Step AbstractOrderingStep */
            return $Step->getType() === Controls\OrderProcess\Checkout::class;
        }));


        if ($Current->showNext() === false) {
            $next = false;
        }

        if ($previous === ''
            || $Current->getName() === $this->getFirstStep()->getName()
        ) {
            $previous = false;
        }

        if ($Current->getName() === $Checkout->getName()) {
            $next           = false;
            $payableToOrder = true;
        }

        try {
            $Current->validate();
        } catch (QUI\ERP\Order\Exception $Exception) {
            $error = $Exception->getMessage();

            if (get_class($Current) === QUI\ERP\Order\Controls\OrderProcess\Finish::class) {
                $Current = $this->getPreviousStep();

                if (method_exists($Current, 'forceSave')) {
                    $Current->forceSave();
                }

                try {
                    $Current->validate();
                    $error = false;
                } catch (\Exception $Exception) {
                    $error   = $Exception->getMessage();
                    $Current = $this->getPreviousStep();
                }
            }

            // if step is the same as the current step, then we need an error message
            // if step is not the same as the current step, then we need not an error message
            if ($this->getAttribute('step') === $Current->getName()) {
                $error = false;
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $Current = $this->getPreviousStep();
        }

        $Project = $this->getSite()->getProject();

        $this->setAttribute('step', $Current->getName());
        $this->setAttribute('data-url', Utils\Utils::getOrderProcess($Project)->getUrlRewritten());

        if ($Current instanceof Controls\OrderProcess\Finish) {
            $next     = false;
            $previous = false;
        }

        $Engine->assign([
            'listWidth'          => floor(100 / count($this->getSteps())),
            'this'               => $this,
            'error'              => $error,
            'next'               => $next,
            'previous'           => $previous,
            'payableToOrder'     => $payableToOrder,
            'steps'              => $this->getSteps(),
            'CurrentStep'        => $Current,
            'currentStepContent' => QUI\ControlUtils::parse($Current),
            'Site'               => $this->getSite(),
            'Order'              => $this->getOrder(),
            'hash'               => $this->getStepHash()
        ]);

        $this->Events->fireEvent('getBody', [$this]);

        return QUI\Output::getInstance()->parse($Engine->fetch($template));
    }

    /**
     * checks if the order is in the payment process
     *if yes, they try to make the payment step
     *
     * @return bool|string
     *
     * @throws Basket\Exception
     * @throws Exception
     * @throws QUI\Exception
     */
    protected function checkProcessing()
    {
        $Current = $this->getCurrentStep();
        $Order   = $this->getOrder();

        if (!$Order) {
            return false;
        }

        $checkedTermsAndConditions = QUI::getSession()->get(
            'termsAndConditions-'.$Order->getHash()
        );

        if ($Order->isSuccessful() || $Order instanceof Order) {
            $checkedTermsAndConditions = true;
        }

        if (!$checkedTermsAndConditions) {
            return false;
        }

        /* @var $Checkout AbstractOrderingStep */
        /* @var $Finish AbstractOrderingStep */
        $Checkout = current(array_filter($this->getSteps(), function ($Step) {
            /* @var $Step AbstractOrderingStep */
            return $Step->getType() === Controls\OrderProcess\Checkout::class;
        }));

        $Finish = current(array_filter($this->getSteps(), function ($Step) {
            /* @var $Step AbstractOrderingStep */
            return $Step->getType() === Controls\OrderProcess\Finish::class;
        }));


        $render = function () use ($Order, $Finish, &$Current) {
            // show processing step
            $Processing = $this->getProcessingStep();

            $this->setAttribute('step', $Processing->getName());

            $Payment = $Order->getPayment();

            if ($Payment->getPaymentType()->isGateway() === false) {
                $this->setAttribute('step', $Finish->getName());
                $Current = $Finish;

                try {
                    $this->send();
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }

                return false;
            }

            if (Settings::getInstance()->get('order', 'failedPaymentProcedure') === 'execute') {
                try {
                    $this->send();
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }
            }

            // rearrange the steps, insert the processing step
            $Steps = $this->parseSteps();
            $Steps->append($Processing);

            $this->sortSteps($Steps);

            $this->steps = $this->parseStepsToArray($Steps);
            $result      = $this->executePayableStatus();

            if ($result === false) {
                return false;
            }

            /* @var $Step AbstractOrderingStep */
            foreach ($this->getSteps() as $Step) {
                try {
                    $Step->onExecutePayableStatus();
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::writeDebugException($Exception);
                }
            }

            return $result;
        };

        if (isset($_REQUEST['payableToOrder'])) {
            return $render();
        }

        // show processing step if order is not paid
        if ($Order instanceof Order && !$Order->isPaid()) {
            // if a payment transaction exists, maybe the transaction is in pending
            // as long as, the order is "successful"
            $transactions = $Order->getTransactions();
            $isInPending  = array_filter($transactions, function ($Transaction) {
                /* @var $Transaction QUI\ERP\Accounting\Payments\Transactions\Transaction */
                return $Transaction->isPending();
            });

            if (!$isInPending) {
                return $render();
            }
        }

        if ($Finish->getName() === $Current->getName()
            && $this->getOrder()->getDataEntry('orderedWithCosts')) {
            return $render();
        }

        if ($Checkout->getName() !== $Current->getName()) {
            return false;
        }

        if ($this->getOrder()->getDataEntry('orderedWithCosts')) {
            return $render();
        }

        return false;
    }

    /**
     * @return mixed
     * @throws Basket\Exception
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\ExceptionStack
     */
    protected function renderFinish()
    {
        $template = dirname(__FILE__).'/Controls/OrderProcess.html';
        $Engine   = QUI::getTemplateManager()->getEngine();

        $Engine->assign([
            'listWidth'          => floor(100 / count($this->getSteps())),
            'this'               => $this,
            'error'              => false,
            'next'               => false,
            'previous'           => false,
            'payableToOrder'     => false,
            'steps'              => $this->getSteps(),
            'CurrentStep'        => $this->getLastStep(),
            'currentStepContent' => QUI\ControlUtils::parse($this->getLastStep()),
            'Site'               => $this->getSite(),
            'Order'              => $this->getOrder(),
            'hash'               => $this->getStepHash()
        ]);

        $this->Events->fireEvent('getBody', [$this]);
        $this->Events->fireEvent('renderFinish', [$this]);

        return QUI\Output::getInstance()->parse($Engine->fetch($template));
    }

    /**
     * Return the current Step
     *
     * @return AbstractOrderingStep
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws Basket\Exception
     */
    public function getCurrentStep()
    {
        $steps   = $this->getSteps();
        $current = $this->getCurrentStepName();

        if (isset($steps[$current])) {
            return $steps[$current];
        }

        $Processing = $this->getProcessingStep();

        if ($current === $Processing->getName()) {
            return $Processing;
        }

        return $this->getFirstStep();
    }

    /**
     * Return the first step
     *
     * @return AbstractOrderingStep
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws Basket\Exception
     */
    public function getFirstStep()
    {
        return array_values($this->getSteps())[0];
    }

    /**
     * @return mixed
     * @throws Basket\Exception
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getLastStep()
    {
        $steps = array_values($this->getSteps());

        return $steps[count($steps) - 1];
    }

    /**
     * Return the next step
     *
     * @param null|AbstractOrderingStep $StartStep
     * @return bool|AbstractOrderingStep
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws Basket\Exception
     */
    public function getNextStep($StartStep = null)
    {
        if ($StartStep === null) {
            $step = $this->getCurrentStepName();
        } else {
            $step = $StartStep->getName();
        }

        $Order = $this->getOrder();

        if (!$Order) {
            return false;
        }

        // special -> processing step
        /* @var $Processing AbstractOrderingStep */
        $Processing = $this->getProcessingStep();

        if ($step === $Processing->getName() && !$Order->isSuccessful()) {
            $this->setAttribute('orderHash', $Order->getHash());

            return $Processing;
        }

        // if order are successful -> then show the finish step
        if ($Order->isSuccessful()) {
            $this->setAttribute('orderHash', $Order->getHash());
            $this->cleanup();

            return new Controls\OrderProcess\Finish([
                'Order' => $Order
            ]);
        }

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
     * @return AbstractOrderingStep
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws Basket\Exception
     */
    public function getPreviousStep($StartStep = null)
    {
        if ($StartStep === null) {
            $step = $this->getCurrentStepName();
        } else {
            $step = $StartStep->getName();
        }

        // special -> processing step
        /* @var $Processing AbstractOrderingStep */
        $Processing = $this->getProcessingStep();
        $steps      = $this->getSteps();

        if ($step === $Processing->getName()) {
            // return checkout step
            QUI::getSession()->set(
                'termsAndConditions-'.$this->getOrder()->getHash(),
                0
            );

            $Checkout = new Controls\OrderProcess\Checkout();

            // get previous previous step
            $keys = array_keys($this->steps);
            $pos  = array_search($Checkout->getName(), $keys);
            $prev = $pos - 1;

            if (isset($keys[$prev])) {
                $key = $keys[$prev];

                if (isset($steps[$key])) {
                    return $steps[$key];
                }
            }

            return $this->getFirstStep();
        }

        $keys = array_keys($steps);
        $pos  = array_search($step, $keys);
        $prev = $pos - 1;

        if (!isset($keys[$prev])) {
            return $this->getFirstStep();
        }

        $key = $keys[$prev];

        if (isset($steps[$key])) {
            return $steps[$key];
        }

        return $this->getFirstStep();
    }

    /**
     * Return the step via its name
     *
     * @param string $name - Name of the step
     * @return bool|AbstractOrderingStep
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws Basket\Exception
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
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws Basket\Exception
     */
    protected function getCurrentStepName()
    {
        $step  = $this->getAttribute('step');
        $steps = $this->getSteps();

        $Processing = $this->getProcessingStep();

        if ($step === $Processing->getName()) {
            return $Processing->getName();
        }

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
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws Basket\Exception
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
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws Basket\Exception
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
     * Return the url to the order process
     *
     * @return string
     */
    public function getUrl()
    {
        try {
            return QUI\ERP\Order\Utils\Utils::getOrderProcess($this->getProject())->getUrlRewritten();
        } catch (QUI\Exception $Exception) {
        }

        return '';
    }

    /**
     * Return the url for a step
     *
     * @param $step
     * @return string
     */
    public function getStepUrl($step)
    {
        $url = $this->getUrl();
        $url = $url.'/'.$step;

        if ($this->getAttribute('orderHash') && $this->Order) {
            $url = $url.'/'.$this->Order->getHash();
        }

        return trim($url);
    }

    /**
     * Return the hash of the order, if the order process needed it
     *
     * @return string
     */
    public function getStepHash()
    {
        if ($this->getAttribute('orderHash') && $this->Order) {
            return $this->Order->getHash();
        }

        return '';
    }

    /**
     * Return the order site
     *
     * @return QUI\Projects\Site
     *
     * @throws QUI\Exception
     */
    public function getSite()
    {
        if ($this->getAttribute('Site')) {
            return $this->getAttribute('Site');
        }

        $Project = QUI::getRewrite()->getProject();

        $sites = $Project->getSitesIds([
            'where' => [
                'type'   => 'quiqqer/order:types/orderingProcess',
                'active' => 1
            ],
            'limit' => 1
        ]);

        if (isset($sites[0])) {
            $Site = $Project->get($sites[0]['id']);

            $this->setAttribute('Site', $Site);

            return $Site;
        }

        return $Project->firstChild();
    }

    /**
     * @return QUI\ERP\Order\OrderInProcess|null
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getOrder()
    {
        if ($this->Order !== null) {
            return $this->Order;
        }

        // for nobody a temporary order cant be created
        // @todo gast bestellung
        if (QUI::getUsers()->isNobodyUser(QUI::getUserBySession())) {
            return null;
        }

        $Orders = QUI\ERP\Order\Handler::getInstance();
        $User   = QUI::getUserBySession();

        try {
            if ($this->getAttribute('orderHash')) {
                $Order = $Orders->getOrderByHash($this->getAttribute('orderHash'));

                if ($Order->getCustomer()->getId() == $User->getId()) {
                    $this->Order = $Order;

                    return $this->Order;
                }
            }
        } catch (QUI\Erp\Order\Exception $Exception) {
        }


        try {
            // select the last order in processing
            $OrderInProcess = $Orders->getLastOrderInProcessFromUser($User);

            if (!$OrderInProcess->getOrderId()) {
                $this->Order = $OrderInProcess;
            }
        } catch (QUI\Erp\Order\Exception $Exception) {
        }

        if ($this->Order === null) {
            // if no order exists, we create one
            $this->Order = QUI\ERP\Order\Factory::getInstance()->createOrderInProcess();
        }

        return $this->Order;
    }

    /**
     * @return Basket\Basket|Basket\BasketGuest
     */
    protected function getBasket()
    {
        if ($this->getAttribute('basketId')) {
            try {
                return new Basket\Basket($this->getAttribute('basketId'));
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        $SessionUser = QUI::getUserBySession();

        if (QUI::getUsers()->isNobodyUser($SessionUser)) {
            return new Basket\BasketGuest();
        }

        try {
            return Handler::getInstance()->getBasketFromUser($SessionUser);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        try {
            return QUI\ERP\Order\Factory::getInstance()->createBasket($SessionUser);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return new Basket\BasketGuest();
    }

    /**
     * Return all steps
     *
     * @return array
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws Basket\Exception
     */
    public function getSteps()
    {
        if (empty($this->steps)) {
            $this->steps = $this->parseStepsToArray($this->parseSteps());
        }

        return $this->steps;
    }

    /**
     * Return the processing step
     * - if the processing step is already initialize, it will be returned
     * - if the processing step is not initialize, a new one will be initialized and returned
     *
     * @return mixed|Controls\OrderProcess\Processing
     * @throws Exception
     * @throws QUI\Exception
     */
    protected function getProcessingStep()
    {
        try {
            $Processing = current(array_filter($this->getSteps(), function ($Step) {
                /* @var $Step AbstractOrderingStep */
                return $Step->getType() === Controls\OrderProcess\Processing::class;
            }));

            if (!empty($Processing)) {
                return $Processing;
            }
        } catch (QUI\Exception $Exception) {
        }

        $Processing = new Controls\OrderProcess\Processing([
            'Order'    => $this->getOrder(),
            'priority' => 40
        ]);

        return $Processing;
    }

    /**
     * Parse the steps and return the OrderProcessSteps List
     *
     * @return OrderProcessSteps
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws Basket\Exception
     */
    protected function parseSteps()
    {
        $Steps     = new OrderProcessSteps();
        $providers = QUI\ERP\Order\Handler::getInstance()->getOrderProcessProvider();

        $Registration = new Controls\OrderProcess\Registration([
            'Basket'   => $this->Basket,
            'Order'    => $this->getOrder(),
            'priority' => 1
        ]);

        $Basket = new Controls\OrderProcess\Basket([
            'Basket'   => $this->getBasket(),
            'Order'    => $this->getOrder(),
            'priority' => 10
        ]);

        $CustomerData = new Controls\OrderProcess\CustomerData([
            'Basket'   => $this->getBasket(),
            'Order'    => $this->getOrder(),
            'priority' => 20
        ]);

//        $Delivery = new Controls\Delivery($params);

        $Checkout = new Controls\OrderProcess\Checkout([
            'Order'    => $this->getOrder(),
            'priority' => 40
        ]);

        $Finish = new Controls\OrderProcess\Finish([
            'Order'    => $this->getOrder(),
            'priority' => 50
        ]);


        // init steps
        if (QUI::getUsers()->isNobodyUser(QUI::getUserBySession())) {
            $Steps->append($Registration);
        }

        $Steps->append($Basket);
        $Steps->append($CustomerData);

        /* @var $Provider QUI\ERP\Order\AbstractOrderProcessProvider */
        foreach ($providers as $Provider) {
            $Provider->initSteps($Steps, $this);
        }

        $Steps->append($Checkout);
        $Steps->append($Finish);

        $this->sortSteps($Steps);

        return $Steps;
    }

    /**
     * @param OrderProcessSteps $Steps
     * @return array
     * @throws Exception
     * @throws QUI\Exception
     */
    protected function parseStepsToArray(OrderProcessSteps $Steps)
    {
        $result = [];

        foreach ($Steps as $Step) {
            $result[$Step->getName()] = $Step;

            $Step->setAttribute('Process', $this);
            $Step->setAttribute('Order', $this->getOrder());
        }

        return $result;
    }

    /**
     * Sort a steps Collection
     *
     * @param OrderProcessSteps $Steps
     */
    protected function sortSteps($Steps)
    {
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
    }
}
