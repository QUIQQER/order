<?php

namespace QUI\ERP\Order\Controls;

use QUI;

/**
 * Class ProcessingStep
 *
 * @package QUI\ERP\Order\Controls
 */
class ProcessingProviderStep extends AbstractOrderingStep
{
    /**
     * @var null|QUI\ERP\Order\AbstractOrderProcessProvider
     */
    protected $ProcessingProvider = null;

    /**
     * @return string
     */
    public function getBody()
    {
        if ($this->ProcessingProvider === null) {
            return '';
        }

        return $this->ProcessingProvider->getDisplay($this->getOrder());
    }

    /**
     * @param QUI\ERP\Order\AbstractOrderProcessProvider $ProcessingProvider
     */
    public function setProcessingProvider(QUI\ERP\Order\AbstractOrderProcessProvider $ProcessingProvider)
    {
        $this->ProcessingProvider = $ProcessingProvider;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'processing';
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return 'fa-check';
    }

    /**
     * @throws QUI\ERP\Order\Exception
     */
    public function validate()
    {
    }

    public function save()
    {
    }
}
