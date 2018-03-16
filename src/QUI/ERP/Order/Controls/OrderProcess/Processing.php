<?php

/**
 * This file contains QUI\ERP\Order\Controls\OrderProcess\Processing
 */

namespace QUI\ERP\Order\Controls\OrderProcess;

use QUI;

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
    protected $content = null;

    /**
     * @var string|null
     */
    protected $title = null;

    /**
     * @var null|QUI\ERP\Order\AbstractOrderProcessProvider
     */
    protected $ProcessingProvider = null;

    /**
     * Basket constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__).'/Processing.css');
    }

    /**
     * @return string
     */
    public function getBody()
    {
        if ($this->ProcessingProvider === null) {
            return '';
        }

        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return '';
        }

        $Engine->assign([
            'display' => $this->ProcessingProvider->getDisplay($this->getOrder(), $this),
            'this'    => $this
        ]);

        return $Engine->fetch(dirname(__FILE__).'/Processing.html');
    }

    /**
     * @param QUI\ERP\Order\AbstractOrderProcessProvider $ProcessingProvider
     */
    public function setProcessingProvider(QUI\ERP\Order\AbstractOrderProcessProvider $ProcessingProvider)
    {
        $this->ProcessingProvider = $ProcessingProvider;
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getName($Locale = null)
    {
        return 'Processing';
    }

    /**
     * @return string
     */
    public function getIcon()
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

    //region title

    /**
     * @param null $Locale
     * @return string
     *
     * @throws \ReflectionException
     */
    public function getTitle($Locale = null)
    {
        if (!empty($this->title)) {
            return $this->title;
        }

        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $Reflection = new \ReflectionClass($this);

        return $Locale->get(
            'quiqqer/order',
            'ordering.step.title.'.$Reflection->getShortName()
        );
    }

    /**
     * Set the step title
     *
     * @param string $title
     */
    public function setTitle($title)
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
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     * @param null $Locale
     * @return string
     */
    public function getContent($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $header      = $Locale->get('quiqqer/order', 'ordering.step.title.CheckoutPayment');
        $description = $Locale->get('quiqqer/order', 'ordering.step.checkoutPayment.text');

        $content = '
        <header>
            <h1>'.$header.'</h1>
        </header>
        <div class="quiqqer-order-step-processing-description">'.$description.'</div>';

        if (!empty($this->content)) {
            $content = $this->content;
        }

        return $content;
    }

    //endregion
}
