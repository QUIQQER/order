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
     * Return the current order
     *
     * @return QUI\ERP\Order\OrderProcess
     */
    public function getOrder()
    {
        return $this->getAttribute('Order');
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
        } catch (QUI\ERP\Order\Exception $Exception) {
            return false;
        }

        return true;
    }

    /**
     * It can be overwritten and can be implemented its own functionality
     * eq: Thus it is possible to display settings without the next button
     *
     * @return bool
     */
    public function showNext()
    {
        return true;
    }
}
