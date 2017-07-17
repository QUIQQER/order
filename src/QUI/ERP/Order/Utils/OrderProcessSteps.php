<?php

/**
 * This file contains QUI\ERP\Order\OrderProcessSteps
 */

namespace QUI\ERP\Order\Utils;

use QUI;

/**
 * Class OrderProcessSteps
 *
 * @package QUI\ERP\Order
 */
class OrderProcessSteps extends QUI\Collection
{
    /**
     * OrderProcessSteps constructor.
     * @param array $children
     */
    public function __construct(array $children = array())
    {
        $this->allowed = array(
            QUI\ERP\Order\Controls\AbstractOrderingStep::class
        );

        parent::__construct($children);
    }
}
