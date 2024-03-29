<?php

/**
 * This file contains QUI\ERP\Order\Controls\AbstractOrderingStep
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
     *
     * @throws \ReflectionException
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
     * Returns a font awesome icon
     * Can be overwritten by each step
     *
     * @return string
     */
    public function getIcon()
    {
        return 'fa fa-shopping-bag';
    }

    /**
     * Return the current order
     *
     * @return QUI\ERP\Order\OrderInProcess
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
     * Has the Step its own form?
     * Canbe overwritten
     *
     * @return bool
     */
    public function hasOwnForm()
    {
        return false;
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

    /**
     * Can be overwritten
     * This method is called when the customer submits an order with costs.
     * So every step can react to the ordering
     */
    public function onExecutePayableStatus()
    {
    }
}
