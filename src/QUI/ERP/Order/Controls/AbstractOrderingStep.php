<?php

/**
 * This file contains QUI\ERP\Order\Controls\AbstractOrderingStep
 */

namespace QUI\ERP\Order\Controls;

use QUI;
use QUI\Locale;
use ReflectionClass;

/**
 * Class OrderingStepInterface
 * @package QUI\ERP\Order\Controls
 */
abstract class AbstractOrderingStep extends QUI\Control implements OrderingStepInterface
{
    /**
     * @param Locale|null $Locale
     * @return string
     */
    public function getTitle(null | QUI\Locale $Locale = null): string
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $Reflection = new ReflectionClass($this);

        return $Locale->get(
            'quiqqer/order',
            'ordering.step.title.' . $Reflection->getShortName()
        );
    }

    /**
     * Returns a font awesome icon
     * Can be overwritten by each step
     *
     * @return string
     */
    public function getIcon(): string
    {
        return 'fa fa-shopping-bag';
    }

    /**
     * Return the current order
     *
     * @return QUI\ERP\Order\AbstractOrder
     */
    public function getOrder(): QUI\ERP\Order\AbstractOrder
    {
        return $this->getAttribute('Order');
    }

    /**
     * Is the step valid?
     *
     * @return bool
     */
    public function isValid(): bool
    {
        try {
            $this->validate();
        } catch (QUI\ERP\Order\Exception) {
            return false;
        }

        return true;
    }

    /**
     * Has the Step its own form?
     * Can be overwritten
     *
     * @return bool
     */
    public function hasOwnForm(): bool
    {
        return false;
    }

    /**
     * It can be overwritten and can be implemented its own functionality
     * eq: Thus it is possible to display settings without the next button
     *
     * @return bool
     */
    public function showNext(): bool
    {
        return true;
    }

    /**
     * Can be overwritten
     * This method is called when the customer submits an order with costs.
     * So every step can react to the ordering
     */
    public function onExecutePayableStatus()
    {
    }
}
