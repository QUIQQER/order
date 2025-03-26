<?php

/**
 * This file contains QUI\ERP\Order\OrderProcess
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\ERP\Order\Controls\AbstractOrderingStep;
use QUI\ERP\Order\Controls\OrderProcess\Processing;
use QUI\ERP\Order\OrderProcess\OrderProcessMessageHandlerInterface;
use QUI\ERP\Order\Utils\OrderProcessSteps;
use QUI\ERP\Order\Controls\OrderProcess\Finish as FinishControl;
use QUI\Exception;

use function array_filter;
use function array_keys;
use function array_search;
use function array_values;
use function call_user_func;
use function class_exists;
use function class_implements;
use function count;
use function current;
use function dirname;
use function end;
use function floor;
use function get_class;
use function in_array;
use function is_a;
use function json_decode;
use function json_encode;
use function key;
use function method_exists;
use function reset;
use function trim;

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
    const MESSAGES_SESSION_KEY = 'quiqqer_order_orderprocess_messages';

    /**
     * @var AbstractOrder|null
     */
    protected ?AbstractOrder $Order = null;

    /**
     * @var Basket\Basket|null
     */
    protected ?Basket\Basket $Basket = null;

    /**
     * @var null|AbstractOrderProcessProvider
     */
    protected ?AbstractOrderProcessProvider $ProcessingProvider = null;

    /**
     * List of order process steps
     *
     * @var array
     */
    protected array $steps = [];

    /**
     * @var QUI\Events\Event
     */
    public QUI\Events\Event $Events;

    /**
     * Basket constructor.
     *
     * @param array $attributes
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function __construct(array $attributes = [])
    {
        $this->setAttributes([
            'Site' => false,
            'data-qui' => 'package/quiqqer/order/bin/frontend/controls/OrderProcess',
            'orderHash' => false,
            'basket' => true, // import basket articles to the order, use the basket
            'basketEditable' => true,
            'backToShopUrl' => false
        ]);

        parent::__construct($attributes);

        if (isset($attributes['Order']) && $attributes['Order'] instanceof AbstractOrder) {
            $this->Order = $attributes['Order'];
        }

        $this->addCSSFile(dirname(__FILE__) . '/Controls/OrderProcess.css');
        $this->Events = new QUI\Events\Event();

        $User = QUI::getUserBySession();
        $isNobody = QUI::getUsers()->isNobodyUser($User);

        if ($isNobody) {
            return;
        }


        // current step
        $steps = $this->getSteps();
        $step = $this->getAttribute('step');
        $Order = $this->getOrder();

        $customerUUID = $Order->getCustomer()->getUUID();
        $userUUID = $User->getUUID();

        if ($customerUUID !== $userUUID && !QUI::getUsers()->isSystemUser($User)) {
            throw new QUI\Permissions\Exception([
                'quiqqer/order',
                'exception.no.permission.for.this.order'
            ]);
        }

        if ($Order->isSuccessful()) {
            $this->setAttribute('orderHash', $Order->getUUID());
            $LastStep = end($steps);

            $this->setAttribute('step', $LastStep->getName());
            $this->setAttribute('orderHash', $Order->getUUID());

            return;
        }


        // basket into the order
        $Basket = $this->getBasket();

        if (!$this->getAttribute('orderHash') && $this->getAttribute('basket')) {
            $Basket->toOrder($Order);
        }

        $this->setAttribute('basketId', $Basket->getId());
        $this->setAttribute('orderHash', $Order->getUUID());

        // set order currency
        $UserCurrency = QUI\ERP\Currency\Handler::getRuntimeCurrency();

        if ($UserCurrency->getCode() !== $Order->getCurrency()->getCode()) {
            $Order->setCurrency($UserCurrency);
            $Order->update();
        }

        // order is successful, so no other step must be shown
        if ($Order->isSuccessful()) {
            $LastStep = end($steps);

            $this->setAttribute('step', $LastStep->getName());
            $this->setAttribute('orderHash', $Order->getUUID());
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

        if (isset($_GET['checkout']) && $_GET['checkout'] == 1) {
            $keys = array_keys($steps);
            $this->setAttribute('step', $keys[1]);
            $step = $keys[1];
        }

        // consider processing step - processing step is ok
        $Processing = $this->getProcessingStep();

        if ($Processing->getName() === $step) {
            $this->setAttribute('orderHash', $Order->getUUID());
            return;
        }

        if (!$step || !isset($steps[$step])) {
            reset($steps);
            $this->setAttribute('step', key($steps));
        }

        QUI::getEvents()->fireEvent('orderProcess', [$this]);
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
     */
    protected function checkSubmission(): void
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
     * Check if the successful status is ok
     */
    protected function checkSuccessfulStatus(): void
    {
        try {
            $Current = $this->getCurrentStep();
        } catch (QUI\Exception) {
            return;
        }

        if (
            !($Current instanceof FinishControl)
            && !($Current instanceof Controls\OrderProcess\Processing)
        ) {
            return;
        }

        try {
            $Payment = $this->getOrder()->getPayment();

            if ($Payment && $Payment->isSuccessful($this->getOrder()->getUUID())) {
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
    protected function send(): void
    {
        $this->Events->fireEvent('sendBegin', [$this]);
        QUI::getEvents()->fireEvent('onQuiqqerOrderProcessSendBegin', [$this]);

        $steps = $this->getSteps();
        $providers = QUI\ERP\Order\Handler::getInstance()->getOrderProcessProvider();

        // #locale quiqqer/order#158
        if (class_exists('QUI\ERP\Products\Handler\Products')) {
            QUI\ERP\Products\Handler\Products::setLocale(QUI::getLocale());
        }

        // check all previous steps
        // is one invalid, go to them
        foreach ($steps as $Step) {
            /* @var $Step AbstractOrderingStep */
            if (
                $Step->getName() === 'Basket'
                || $Step->getName() === 'Checkout'
                || $Step->getName() === 'Finish'
            ) {
                continue;
            }

            try {
                $Step->validate();
            } catch (Exception) {
                $this->setAttribute('current', $Step->getName());
            }
        }

        QUI::getEvents()->fireEvent('orderStart', [$this]);

        // Gehe die verschiedenen Processing Provider durch
        $OrderInProcess = $this->getOrder();
        $success = [];

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

        QUI::getEvents()->fireEvent('onQuiqqerOrderProcessSendCreateOrder', [$this]);

        // all runs fine
        if ($OrderInProcess instanceof OrderInProcess) {
            $Order = $OrderInProcess->createOrder();
            $OrderInProcess->delete();

            $this->Order = $Order;
        } else {
            $this->Order = $OrderInProcess;
        }

        $this->setAttribute('orderHash', $this->Order->getUUID());
        $this->setAttribute('current', 'Finish');
        $this->setAttribute('step', 'Finish');

        // set all to successful
        $this->cleanup();

        $this->Events->fireEvent('send', [$this, $this->Order]);
        QUI::getEvents()->fireEvent('onQuiqqerOrderProcessSend', [$this]);
    }

    /**
     * Cleanup stuff, look if smth is not needed anymore
     */
    protected function cleanup(): void
    {
        // set all to successful
        if (!$this->Order->isSuccessful()) {
            return;
        }

        // if temp order exist, and a normal order kill it
        try {
            $ProcessOrder = Handler::getInstance()->getOrderInProcessByHash(
                $this->Order->getUUID()
            );

            $Order = Handler::getInstance()->getOrderByHash(
                $ProcessOrder->getUUID()
            );

            if ($Order instanceof Order) {
                $ProcessOrder->delete();
            }

            $this->Order = $Order;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        if ($this->Basket && method_exists($this->Basket, 'successful')) {
            $this->Basket->successful();
            $this->Basket->save();
        } else {
            try {
                $Basket = Handler::getInstance()->getBasketByHash($this->Order->getUUID());
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
     * @throws QUI\Exception|\Exception
     */
    protected function executePayableStatus(): bool | string
    {
        $template = dirname(__FILE__) . '/Controls/OrderProcess.html';
        $Engine = QUI::getTemplateManager()->getEngine();

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
                $Payment = $this->getOrder()->getPayment();

                if (Settings::getInstance()->get('paymentChangeable', $Payment->getId())) {
                    $changePayment = true;
                }

                $Engine->assign([
                    'listWidth' => floor(100 / count($this->getSteps())),
                    'this' => $this,
                    'error' => false,
                    'next' => false,
                    'previous' => false,
                    'payableToOrder' => false,
                    'changePayment' => $changePayment,
                    'steps' => $this->getSteps(),
                    'CurrentStep' => $ProcessingStep,
                    'currentStepContent' => QUI\ControlUtils::parse($ProcessingStep),
                    'Site' => $this->getSite(),
                    'Order' => $this->getOrder(),
                    'hash' => $this->getStepHash(),
                    'backToShopUrl' => $this->getBackToShopUrl()
                ]);

                return QUI\Output::getInstance()->parse($Engine->fetch($template));
            }

            $Engine->assign([
                'listWidth' => floor(100 / count($this->getSteps())),
                'this' => $this,
                'error' => false,
                'next' => false,
                'previous' => false,
                'payableToOrder' => false,
                'steps' => $this->getSteps(),
                'CurrentStep' => $this->getCurrentStep(),
                'currentStepContent' => QUI\ControlUtils::parse($this->getCurrentStep()),
                'Site' => $this->getSite(),
                'Order' => $this->getOrder(),
                'hash' => $this->getStepHash(),
                'backToShopUrl' => $this->getBackToShopUrl()
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
    public function getBody(): string
    {
        $this->Events->fireEvent('getBodyBegin', [$this]);
        QUI::getEvents()->fireEvent('quiqqerOrderOrderProcessGetBodyBegin', [$this]);

        $this->setAttribute(
            'data-qui-option-basketeditable',
            $this->getAttribute('basketEditable') ? 1 : 0
        );

        $User = QUI::getUserBySession();
        $isNobody = QUI::getUsers()->isNobodyUser($User);
        $template = dirname(__FILE__) . '/Controls/OrderProcess.html';
        $Engine = QUI::getTemplateManager()->getEngine();

        if ($isNobody) {
            $guestOrderInstalled = QUI::getPackageManager()->isInstalled('quiqqer/order-guestorder');
            $GuestOrder = null;
            $nobodyIntroTitle = '';
            $nobodyIntroDesc = '';
            $Site = $this->getSite();

            if (
                class_exists('QUI\ERP\Order\Guest\GuestOrder')
                && class_exists('QUI\ERP\Order\Guest\Controls\GuestOrderButton')
                && $guestOrderInstalled
                && QUI\ERP\Order\Guest\GuestOrder::isActive()
            ) {
                $GuestOrder = new QUI\ERP\Order\Guest\Controls\GuestOrderButton();

                if ($Site->getAttribute('quiqqer.order.nobody.intro.title')) {
                    $nobodyIntroTitle = $Site->getAttribute('quiqqer.order.nobody.intro.title');
                }

                if ($Site->getAttribute('quiqqer.order.nobody.intro.desc')) {
                    $nobodyIntroDesc = $Site->getAttribute('quiqqer.order.nobody.intro.desc');
                }
            }

            $Request = QUI::getRequest();
            $url = $Request->getRequestUri();

            $activeEntry = match (true) {
                str_contains($url, '?open=login') => 'login',
                str_contains($url, '?open=signup') => 'signup',
                default => 'login'
            };

            if (
                $guestOrderInstalled
                && class_exists('QUI\ERP\Order\Guest\GuestOrder')
                && str_contains($url, '?open=guest')
                && QUI\ERP\Order\Guest\GuestOrder::isActive()
            ) {
                $activeEntry = 'guest';
            }

            $Engine->assign([
                'Registration' => new Controls\Checkout\Registration([
                    'autofill' => false
                ]),
                'Login' => new Controls\Checkout\Login(),
                'guestOrderInstalled' => $guestOrderInstalled,
                'GuestOrder' => $GuestOrder,
                'activeEntry' => $activeEntry,
                'nobodyIntroTitle' => $nobodyIntroTitle,
                'nobodyIntroDesc' => $nobodyIntroDesc
            ]);

            return $Engine->fetch(
                dirname(__FILE__) . '/Controls/OrderProcess.Nobody.html'
            );
        }

        // check if processing step is needed
        $processing = $this->checkProcessing();

        if (!empty($processing)) {
            QUI::getEvents()->fireEvent('quiqqerOrderProcessingStart', [$this]);

            return $processing;
        }

        // standard procedure
        $steps = $this->getSteps();
        $LastStep = end($steps);

        $this->checkSubmission();
        $this->checkSuccessfulStatus();

        // check if order is finished
        $Order = $this->getOrder();

        if ($Order && $Order->isSuccessful()) {
            if ($Order instanceof OrderInProcess && !$Order->getOrderId()) {
                $this->send();
                $Order = $this->Order;
            }

            $this->setAttribute('step', $LastStep->getName());
            $this->setAttribute('orderHash', $Order->getUUID());

            return $this->renderFinish();
        }

        $Current = $this->getCurrentStep();

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

        $error = false;
        $next = $this->getNextStepName($Current);
        $previous = $this->getPreviousStepName();

        $payableToOrder = false;

        /* @var $Checkout AbstractOrderingStep */
        $Checkout = current(
            array_filter($this->getSteps(), function ($Step) {
                /* @var $Step AbstractOrderingStep */
                return $Step->getType() === Controls\OrderProcess\Checkout::class;
            })
        );


        if ($Current->showNext() === false) {
            $next = false;
        }

        if (
            $previous === ''
            || $Current->getName() === $this->getFirstStep()->getName()
        ) {
            $previous = false;
        }

        if ($Current->getName() === $Checkout->getName()) {
            $next = false;
            $payableToOrder = true;
        }

        try {
            $Current->validate();

            $this->Events->fireEvent('validate', [$this]);
            QUI::getEvents()->fireEvent('quiqqerOrderOrderProcessValidate', [$this]);
        } catch (QUI\ERP\Order\Exception $Exception) {
            $error = $Exception->getMessage();

            if (get_class($Current) === FinishControl::class) {
                $Current = $this->getPreviousStep();

                if (method_exists($Current, 'forceSave')) {
                    $Current->forceSave();
                }

                try {
                    $Current->validate();
                    $error = false;
                } catch (\Exception $Exception) {
                    $error = $Exception->getMessage();
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

        if ($Current instanceof FinishControl) {
            $next = false;
            $previous = false;
        }

        $frontendMessages = [];
        $FrontendMessages = $Order->getFrontendMessages();

        if (!$FrontendMessages->isEmpty()) {
            $frontendMessages = $FrontendMessages->toArray();
            $Order->clearFrontendMessages();
        }

        // #locale quiqqer/order#158
        if (class_exists('QUI\ERP\Products\Handler\Products')) {
            QUI\ERP\Products\Handler\Products::setLocale(QUI::getLocale());
        }

        $Engine->assign([
            'listWidth' => floor(100 / count($this->getSteps())),
            'this' => $this,
            'error' => $error,
            'next' => $next,
            'previous' => $previous,
            'payableToOrder' => $payableToOrder,
            'steps' => $this->getSteps(),
            'CurrentStep' => $Current,
            'currentStepContent' => QUI\ControlUtils::parse($Current),
            'Site' => $this->getSite(),
            'Order' => $this->getOrder(),
            'hash' => $this->getStepHash(),
            'messages' => $this->getStepMessages(get_class($Current)),
            'frontendMessages' => $frontendMessages,
            'backToShopUrl' => $this->getBackToShopUrl()
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
     * @throws Exception
     * @throws QUI\Exception
     */
    protected function checkProcessing(): bool | string
    {
        $Current = $this->getCurrentStep();
        $Order = $this->getOrder();

        if (!$Order) {
            return false;
        }

        if (!$Order->getPayment()) {
            return false;
        }

        $checkedTermsAndConditions = QUI::getSession()->get(
            'termsAndConditions-' . $Order->getUUID()
        );

        if ($Order->isSuccessful() || $Order instanceof Order) {
            $checkedTermsAndConditions = true;
        }

        if (!$checkedTermsAndConditions) {
            return false;
        }

        /* @var $Checkout AbstractOrderingStep */
        /* @var $Finish AbstractOrderingStep */
        $Checkout = current(
            array_filter($this->getSteps(), function ($Step) {
                /* @var $Step AbstractOrderingStep */
                return $Step->getType() === Controls\OrderProcess\Checkout::class;
            })
        );

        $Finish = current(
            array_filter($this->getSteps(), function ($Step) {
                /* @var $Step AbstractOrderingStep */
                return $Step->getType() === FinishControl::class;
            })
        );


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
            $result = $this->executePayableStatus();

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
        $paymentIsSuccessful = false;
        $Payment = $Order->getPayment();

        // @phpstan-ignore-next-line
        if ($Payment && $Payment->isSuccessful($Order->getUUID())) {
            $paymentIsSuccessful = true;
        }

        if ($Order instanceof Order && !$Order->isPaid() && !$paymentIsSuccessful) {
            // if a payment transaction exists, maybe the transaction is in pending
            // as long as, the order is "successful"
            $transactions = $Order->getTransactions();
            $isInPending = array_filter($transactions, function ($Transaction) {
                /* @var $Transaction QUI\ERP\Accounting\Payments\Transactions\Transaction */
                return $Transaction->isPending();
            });

            if (!$isInPending) {
                return $render();
            }
        }

        if (
            $Finish->getName() === $Current->getName()
            && $this->getOrder()->getDataEntry('orderedWithCosts')
        ) {
            return $render();
        }

        if ($Checkout && $Checkout->getName() !== $Current->getName()) {
            return false;
        }

        if ($this->getOrder()->getDataEntry('orderedWithCosts')) {
            return $render();
        }

        return false;
    }

    /**
     * Render the last step (finish step)
     *
     * @return mixed
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\ExceptionStack
     */
    protected function renderFinish(): mixed
    {
        $Order = $this->getOrder();

        if ($Order instanceof Order) {
            $this->Order = null;
        }

        $Basket = $this->getBasket();

        // clear basket
        if ($Basket instanceof Basket\Basket) {
            $Basket->clear();
        }

        $template = dirname(__FILE__) . '/Controls/OrderProcess.html';
        $Engine = QUI::getTemplateManager()->getEngine();

        $steps = $this->getSteps();
        $LastStep = $this->getLastStep();
        $Site = $this->getSite();
        $stepHash = $this->getStepHash();
        $stepControl = QUI\ControlUtils::parse($LastStep);

        $Engine->assign([
            'listWidth' => floor(100 / count($steps)),
            'this' => $this,
            'error' => false,
            'next' => false,
            'previous' => false,
            'payableToOrder' => false,
            'steps' => $steps,
            'CurrentStep' => $LastStep,
            'currentStepContent' => $stepControl,
            'Site' => $Site,
            'Order' => $Order,
            'hash' => $stepHash,
            'backToShopUrl' => $this->getBackToShopUrl()
        ]);

        $this->Events->fireEvent('getBody', [$this]);
        $this->Events->fireEvent('renderFinish', [$this]);

        return QUI\Output::getInstance()->parse($Engine->fetch($template));
    }

    /**
     * Return the current Step
     *
     * @return Processing|AbstractOrderingStep
     *
     * @throws Exception
     * @throws Exception
     */
    public function getCurrentStep(): Controls\OrderProcess\Processing | AbstractOrderingStep
    {
        $steps = $this->getSteps();
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
     */
    public function getFirstStep(): AbstractOrderingStep
    {
        return array_values($this->getSteps())[0];
    }

    /**
     * Returns the last step of the order process
     *
     * @return mixed
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getLastStep(): mixed
    {
        $steps = array_values($this->getSteps());

        return $steps[count($steps) - 1];
    }

    /**
     * Return the next step
     *
     * @param AbstractOrderingStep|null $StartStep
     * @return FinishControl|bool|AbstractOrderingStep
     *
     * @throws Exception
     */
    public function getNextStep(
        null | AbstractOrderingStep $StartStep = null
    ): FinishControl | bool | AbstractOrderingStep {
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
            $this->setAttribute('orderHash', $Order->getUUID());

            return $Processing;
        }

        // if order are successful -> then show the finish step
        if ($Order->isSuccessful()) {
            $this->setAttribute('orderHash', $Order->getUUID());
            $this->cleanup();

            return new Controls\OrderProcess\Finish([
                'Order' => $Order
            ]);
        }

        $steps = $this->getSteps();

        $keys = array_keys($steps);
        $pos = array_search($step, $keys);
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
     * @param AbstractOrderingStep|null $StartStep
     * @return AbstractOrderingStep|null
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getPreviousStep(null | AbstractOrderingStep $StartStep = null): ?AbstractOrderingStep
    {
        if ($StartStep === null) {
            $step = $this->getCurrentStepName();
        } else {
            $step = $StartStep->getName();
        }

        // special -> processing step
        /* @var $Processing AbstractOrderingStep */
        $Processing = $this->getProcessingStep();
        $steps = $this->getSteps();

        if ($step === $Processing->getName()) {
            // return checkout step
            QUI::getSession()->set(
                'termsAndConditions-' . $this->getOrder()->getUUID(),
                0
            );

            $Checkout = new Controls\OrderProcess\Checkout();

            // get previous previous step
            $keys = array_keys($this->steps);
            $pos = array_search($Checkout->getName(), $keys);
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
        $pos = array_search($step, $keys);
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
     */
    protected function getStepByName(string $name): bool | AbstractOrderingStep
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
     */
    protected function getCurrentStepName(): string
    {
        $step = $this->getAttribute('step');
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
     * @param AbstractOrderingStep|null $StartStep
     * @return bool|string
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    protected function getNextStepName(null | AbstractOrderingStep $StartStep = null): bool | string
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
     * @param AbstractOrderingStep|null $StartStep
     * @return bool|string
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    protected function getPreviousStepName(null | AbstractOrderingStep $StartStep = null): bool | string
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
    public function getUrl(): string
    {
        try {
            return QUI\ERP\Order\Utils\Utils::getOrderProcess($this->getProject())->getUrlRewritten();
        } catch (\Exception) {
        }

        return '';
    }

    /**
     * Return the url for a step
     *
     * @param $step
     * @return string
     */
    public function getStepUrl($step): string
    {
        $url = $this->getUrl();
        $url = $url . '/' . $step;

        if ($this->getAttribute('orderHash') && $this->Order) {
            $url = $url . '/' . $this->Order->getUUID();
        }

        return trim($url);
    }

    /**
     * Return the hash of the order, if the order process needed it
     *
     * @return string
     */
    public function getStepHash(): string
    {
        if ($this->getAttribute('orderHash') && $this->Order) {
            return $this->Order->getUUID();
        }

        return '';
    }

    /**
     * Return the order site
     *
     * @return QUI\Interfaces\Projects\Site
     * @throws QUI\Exception
     */
    public function getSite(): QUI\Interfaces\Projects\Site
    {
        if ($this->getAttribute('Site')) {
            return $this->getAttribute('Site');
        }

        $Project = QUI::getRewrite()->getProject();

        $sites = $Project->getSitesIds([
            'where' => [
                'type' => 'quiqqer/order:types/orderingProcess',
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
     * @return AbstractOrder|null
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getOrder(): ?AbstractOrder
    {
        if ($this->Order !== null) {
            return $this->Order;
        }

        try {
            $result = QUI::getEvents()->fireEvent('orderProcessGetOrder', [$this]);

            if (!empty($result)) {
                $OrderInstance = null;

                foreach ($result as $entry) {
                    if ($entry && in_array(OrderInterface::class, class_implements($entry))) {
                        $OrderInstance = $entry;
                    }
                }

                if ($OrderInstance && in_array(OrderInterface::class, class_implements($OrderInstance))) {
                    $this->Order = $OrderInstance;
                    return $this->Order;
                }
            }
        } catch (\Exception) {
        }


        $User = QUI::getUserBySession();

        // for nobody a temporary order cant be created
        if (QUI::getUsers()->isNobodyUser($User)) {
            return null;
        }

        $Orders = QUI\ERP\Order\Handler::getInstance();
        $User = QUI::getUserBySession();

        try {
            if ($this->getAttribute('orderHash')) {
                $Order = $Orders->getOrderByHash($this->getAttribute('orderHash'));

                if (
                    QUI::getUsers()->isSystemUser($User)
                    || $Order->getCustomer()->getUUID() === $User->getUUID()
                ) {
                    $this->Order = $Order;

                    return $this->Order;
                }
            }
        } catch (QUI\ERP\Order\Exception) {
        }


        try {
            // select the last order in processing
            $OrderInProcess = $Orders->getLastOrderInProcessFromUser($User);

            if (!$OrderInProcess->getOrderId()) {
                $this->Order = $OrderInProcess;
            }
        } catch (QUI\ERP\Order\Exception) {
        }

        if ($this->Order === null) {
            // if no order exists, we create one
            $this->Order = QUI\ERP\Order\Factory::getInstance()->createOrderInProcess();
        }

        return $this->Order;
    }

    /**
     * @return Basket\Basket|Basket\BasketGuest|QUI\ERP\Order\Basket\BasketOrder
     */
    protected function getBasket(): Basket\BasketGuest | Basket\Basket | Basket\BasketOrder
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
            if ($this->Order && $this->getAttribute('step')) {
                return new QUI\ERP\Order\Basket\BasketOrder(
                    $this->Order->getUUID(),
                    $SessionUser
                );
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
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
     */
    public function getSteps(): array
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
    protected function getProcessingStep(): mixed
    {
        try {
            $Processing = current(
                array_filter($this->getSteps(), function ($Step) {
                    /* @var $Step AbstractOrderingStep */
                    return $Step->getType() === Controls\OrderProcess\Processing::class;
                })
            );

            if (!empty($Processing)) {
                return $Processing;
            }
        } catch (QUI\Exception) {
        }

        // @todo process step sich merken, sonst 1000 neue objekte
        return new Controls\OrderProcess\Processing([
            'Order' => $this->getOrder(),
            'priority' => 40
        ]);
    }

    /**
     * Parse the steps and return the OrderProcessSteps List
     *
     * @return OrderProcessSteps
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    protected function parseSteps(): OrderProcessSteps
    {
        $Steps = new OrderProcessSteps();
        $providers = QUI\ERP\Order\Handler::getInstance()->getOrderProcessProvider();

        $Order = $this->getOrder();
        $Basket = $this->Basket;

        QUI::getEvents()->fireEvent('onQuiqqerOrderProcessStepsBegin', [$this, $Order, $Steps]);

        if (QUI::getUsers()->isNobodyUser(QUI::getUserBySession())) {
            $Steps->append(
                new Controls\OrderProcess\Registration([
                    'Basket' => $Basket,
                    'Order' => $Order,
                    'priority' => 1
                ])
            );

            QUI::getEvents()->fireEvent('onQuiqqerOrderProcessStepsEnd', [$this, $Order, $Steps]);

            return $Steps;
        }

        if ($Order instanceof OrderInProcess) {
            $Basket = $this->getBasket();
        }

        if ($Order && $Order->isSuccessful()) {
            $Finish = new Controls\OrderProcess\Finish([
                'Order' => $Order,
                'priority' => 50
            ]);

            $Steps->append($Finish);

            QUI::getEvents()->fireEvent('onQuiqqerOrderProcessStepsEnd', [$this, $Order, $Steps]);

            return $Steps;
        }

        /*
        $Registration = new Controls\OrderProcess\Registration([
            'Basket' => $Basket,
            'Order' => $Order,
            'priority' => 1
        ]);
        */

        $Basket = new Controls\OrderProcess\Basket([
            'Basket' => $Basket,
            'Order' => $Order,
            'priority' => 10,
            'editable' => $this->getAttribute('basketEditable')
        ]);

        $CustomerData = new Controls\OrderProcess\CustomerData([
            'Basket' => $Basket,
            'Order' => $Order,
            'priority' => 20
        ]);

        $Checkout = new Controls\OrderProcess\Checkout([
            'Order' => $Order,
            'priority' => 40
        ]);

        $Finish = new Controls\OrderProcess\Finish([
            'Order' => $Order,
            'priority' => 50
        ]);


        // init steps
        $Steps->append($Basket);
        $Steps->append($CustomerData);

        /* @var $Provider QUI\ERP\Order\AbstractOrderProcessProvider */
        foreach ($providers as $Provider) {
            $Provider->initSteps($Steps, $this);
        }

        $Steps->append($Checkout);
        $Steps->append($Finish);

        foreach ($Steps as $Step) {
            $Step->setAttribute('OrderProcess', $this);
        }

        $this->sortSteps($Steps);


        QUI::getEvents()->fireEvent('onQuiqqerOrderProcessStepsEnd', [$this, $Order, $Steps]);

        return $Steps;
    }

    /**
     * @param OrderProcessSteps $Steps
     * @return array
     * @throws Exception
     * @throws QUI\Exception
     */
    protected function parseStepsToArray(OrderProcessSteps $Steps): array
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
    protected function sortSteps(OrderProcessSteps $Steps): void
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

    /**
     * Add a OrderProcessMessage (by ID) that is displayed the next time
     * a specific OrderStep is loaded.
     *
     * @param int $id
     * @param string $messageHandler - Must implement OrderProcessMessageHandlerInterface
     * @param string $orderStep - Class of the OrderStep the message is shown in
     * (must implement QUI\ERP\Order\Controls\AbstractOrderingStep)
     * @return void
     */
    public function addStepMessage(
        int $id,
        string $messageHandler,
        string $orderStep
    ): void {
        if (!is_a($orderStep, AbstractOrderingStep::class, true)) {
            return;
        }

        if (!is_a($messageHandler, OrderProcessMessageHandlerInterface::class, true)) {
            return;
        }

        $Session = QUI::getSession();
        $messages = $Session->get(self::MESSAGES_SESSION_KEY);

        if (empty($messages)) {
            $messages = [];
        } else {
            $messages = json_decode($messages, true);
        }

        if (!isset($messages[$orderStep])) {
            $messages[$orderStep] = [];
        }

        $messages[$orderStep][] = [
            'id' => $id,
            'messageHandler' => $messageHandler
        ];

        $Session->set(self::MESSAGES_SESSION_KEY, json_encode($messages));
    }

    /**
     * Clears all step messages
     *
     * @return void
     */
    public function clearStepMessages(): void
    {
        QUI::getSession()->set(self::MESSAGES_SESSION_KEY, null);
    }

    /**
     * Get all messages for an order step
     *
     * @param string $orderStep - Class of the OrderStep the message is shown in
     * (must implement QUI\ERP\Order\Controls\AbstractOrderingStep)
     * @return QUI\ERP\Order\OrderProcess\OrderProcessMessage[]
     */
    protected function getStepMessages(string $orderStep): array
    {
        $messages = [];
        $Session = QUI::getSession();
        $savedMessages = $Session->get(self::MESSAGES_SESSION_KEY);

        if (empty($savedMessages)) {
            return $messages;
        } else {
            $savedMessages = json_decode($savedMessages, true);
        }

        if (empty($savedMessages[$orderStep])) {
            return $messages;
        }

        foreach ($savedMessages[$orderStep] as $k => $messageData) {
            $Message = call_user_func([$messageData['messageHandler'], 'getMessage'], $messageData['id']);

            if ($Message) {
                $messages[] = $Message;
                unset($savedMessages[$orderStep][$k]);
            }
        }

        $Session->set(self::MESSAGES_SESSION_KEY, json_encode($savedMessages));

        return $messages;
    }

    /**
     * Return the url to the shop
     * this is the url that sets the link at "back to shop
     *
     * @return string
     * @throws QUI\Exception
     */
    protected function getBackToShopUrl(): string
    {
        $Project = $this->getSite()->getProject();

        if (Settings::getInstance()->get('orderProcess', 'backToShopUrl')) {
            $url = Settings::getInstance()->get('orderProcess', 'backToShopUrl');

            if (QUI\Projects\Site\Utils::isSiteLink($url)) {
                try {
                    return QUI\Projects\Site\Utils::getSiteByLink($url)->getUrlRewritten();
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::addDebug($Exception->getTraceAsString());
                }
            }
        }

        if ($this->getAttribute('backToShopUrl')) {
            return $this->getAttribute('backToShopUrl');
        }

        return $Project->firstChild()->getUrlRewritten();
    }
}
