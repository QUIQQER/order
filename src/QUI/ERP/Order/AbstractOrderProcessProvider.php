<?php

/**
 * This file contains QUI\ERP\Order\AbstractOrderProcessProvider
 */

namespace QUI\ERP\Order;

use QUI\ERP\Order\OrderProcess;
use QUI\ERP\Order\Utils\OrderProcessSteps;

/**
 * Class AbstractOrderProcessProvider
 *
 * @package QUI\ERP\Order
 */
abstract class AbstractOrderProcessProvider
{
    /**
     * Can be overwritten
     *
     * @param OrderProcessSteps $OrderProcessSteps
     * @param OrderProcess $Order
     */
    public function initSteps(OrderProcessSteps $OrderProcessSteps, OrderProcess $Order)
    {
    }


    public function onOrderStart()
    {
    }

    public function onOrderSuccess()
    {
    }

    public function onOrderAbort()
    {
    }
}
