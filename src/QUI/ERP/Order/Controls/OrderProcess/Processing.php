<?php

/**
 * This file contains QUI\ERP\Order\Controls\OrderProcess\Processing
 */

namespace QUI\ERP\Order\Controls\OrderProcess;

use Exception;
use QUI;
use QUI\Locale;
use ReflectionClass;
use ReflectionException;

use function class_exists;
use function dirname;

/**
 * Class ProcessingStep
 * - payment processing, if needed
 *
 * @package QUI\ERP\Order\Controls
 */
class Processing extends QUI\ERP\Order\Controls\AbstractOrderingStep
{
    /**
     * @var string|null
     */
    protected ?string $content = null;

    /**
     * @var string|null
     */
    protected ?string $title = null;

    /**
     * @var null|QUI\ERP\Order\AbstractOrderProcessProvider
     */
    protected ?QUI\ERP\Order\AbstractOrderProcessProvider $ProcessingProvider = null;

    /**
     * Basket constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setAttribute('nodeName', 'section');

        $this->addCSSClass('quiqqer-order-step-processing');
        $this->addCSSClass('quiqqer-order-step-processing-gateway');
        $this->addCSSFile(dirname(__FILE__) . '/Processing.css');
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        if ($this->ProcessingProvider === null) {
            return '';
        }

        $Engine = QUI::getTemplateManager()->getEngine();

        try {
            $display = $this->ProcessingProvider->getDisplay($this->getOrder(), $this);
            $hasErrors = $this->ProcessingProvider->hasErrors();
        } catch (Exception $Exception) {
            QUI\System\Log::write($Exception->getMessage());

            $hasErrors = true;
            $display = '<div class="message-error">' .
                QUI::getLocale()->get('quiqqer/order', 'exception.processing.error') .
                '</div>';
        }

        $Engine->assign([
            'display' => $display,
            'hasErrors' => $hasErrors,
            'this' => $this
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/Processing.html');
    }

    /**
     * Return the processing payments, if the current payment does not work
     * This method checks if the payment can be changed
     *
     * @return string
     */
    public function getProcessingPayments(): string
    {
        if (!class_exists('\QUI\ERP\Accounting\Payments\Order\Payment')) {
            return '';
        }

        // check if payment can be changed
        $Order = $this->getOrder();
        $Payment = $Order->getPayment();

        if ($Payment && QUI\ERP\Order\Utils\Utils::isPaymentChangeable($Payment) === false) {
            return '';
        }

        try {
            $Engine = QUI::getTemplateManager()->getEngine();

            $PaymentStep = new QUI\ERP\Accounting\Payments\Order\Payment([
                'Order' => $this->getOrder()
            ]);

            $Engine->assign([
                'this' => $this,
                'PaymentStep' => $PaymentStep
            ]);
        } catch (Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return '';
        }

        return $Engine->fetch(dirname(__FILE__) . '/ProcessingPayments.html');
    }

    /**
     * @param QUI\ERP\Order\AbstractOrderProcessProvider $ProcessingProvider
     */
    public function setProcessingProvider(QUI\ERP\Order\AbstractOrderProcessProvider $ProcessingProvider): void
    {
        $this->ProcessingProvider = $ProcessingProvider;
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getName($Locale = null): string
    {
        return 'Processing';
    }

    /**
     * @return string
     */
    public function getIcon(): string
    {
        return 'fa-check';
    }

    /**
     *
     */
    public function validate()
    {
    }

    /**
     * placeholder
     */
    public function save()
    {
    }

    /**
     * Save the payment to the order
     *
     * @param $payment
     * @return void
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function savePayment($payment): void
    {
        if (!class_exists('\QUI\ERP\Accounting\Payments\Order\Payment')) {
            return;
        }

        // check if payment can be changed
        $Order = $this->getOrder();
        $Payment = $Order->getPayment();

        if (QUI\ERP\Order\Utils\Utils::isPaymentChangeable($Payment) === false) {
            return;
        }

        $PaymentStep = new QUI\ERP\Accounting\Payments\Order\Payment([
            'Order' => $this->getOrder(),
            'payment' => $payment
        ]);

        $PaymentStep->save();
    }

    //region title

    /**
     * @param Locale|null $Locale
     * @return string
     */
    public function getTitle(QUI\Locale $Locale = null): string
    {
        if (!empty($this->title)) {
            return $this->title;
        }

        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        try {
            $Reflection = new ReflectionClass($this);
            $className = $Reflection->getShortName();
        } catch (ReflectionException) {
            $className = 'Processing';
        }

        return $Locale->get(
            'quiqqer/order',
            'ordering.step.title.' . $className
        );
    }

    /**
     * Set the step title
     *
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    //endregion

    //region content

    /**
     * Set the step content
     *
     * @param string $content
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    /**
     * @param Locale|null $Locale
     * @return string|null
     */
    public function getContent(QUI\Locale $Locale = null): ?string
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $header = $Locale->get('quiqqer/order', 'ordering.step.title.CheckoutPayment');
        $description = $Locale->get('quiqqer/order', 'ordering.step.checkoutPayment.text');

        $content = '
        <header>
            <h1>' . $header . '</h1>
        </header>
        <div class="quiqqer-order-step-processing-description">' . $description . '</div>';

        if (!empty($this->content)) {
            $content = $this->content;
        }

        return $content;
    }

    //endregion
}
