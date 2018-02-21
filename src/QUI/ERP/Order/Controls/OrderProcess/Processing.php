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
     * @var null|QUI\ERP\Order\AbstractOrderProcessProvider
     */
    protected $ProcessingProvider = null;

    /**
     * Basket constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = array())
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
            'display' => $this->ProcessingProvider->getDisplay($this->getOrder())
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
}
