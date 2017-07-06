<?php

/**
 *
 */

namespace QUI\ERP\Order\Controls;

use QUI;

/**
 * Class OrderingStepInterface
 * @package QUI\ERP\Order\Controls
 */
abstract class AbstractOrderingStep extends QUI\Control implements OrderingStepInterface
{
    /**
     * @param null $Locale
     * @return string
     */
    public function getTitle($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $Reflection = new \ReflectionClass($this);

        return $Locale->get(
            'quiqqer/order',
            'ordering.step.title.' . $Reflection->getShortName()
        );
    }

    /**
     * Is the step valid?
     *
     * @return bool
     */
    public function isValid()
    {
        try {
            $this->validate();
        } catch (QUI\ERP\Order\Exception $exception) {
            return false;
        }

        return true;
    }
}
