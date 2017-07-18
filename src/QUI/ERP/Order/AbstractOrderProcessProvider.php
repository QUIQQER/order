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
    const PROCESSING_STATUS_START = 1;

    const PROCESSING_STATUS_PROCESSING = 2;

    const PROCESSING_STATUS_FINISH = 3;

    const PROCESSING_STATUS_ABORT = 4;

    /**
     * Can be overwritten
     *
     * @param OrderProcessSteps $OrderProcessSteps
     * @param OrderProcess $Order
     * @return void
     */
    public function initSteps(OrderProcessSteps $OrderProcessSteps, OrderProcess $Order)
    {
    }

    /**
     * Can be overwritten
     *
     * @param AbstractOrder $Order
     * @return int - Processing status
     */
    public function onOrderStart(AbstractOrder $Order)
    {
        return self::PROCESSING_STATUS_FINISH;
    }

    /**
     * Can be overwritten
     *
     * @param AbstractOrder $Order
     * @return integer
     */
    public function onOrderSuccess(AbstractOrder $Order)
    {
        return self::PROCESSING_STATUS_FINISH;
    }

    /**
     * Can be overwritten
     *
     * @param AbstractOrder $Order
     * @return integer
     */
    public function onOrderAbort(AbstractOrder $Order)
    {
        return self::PROCESSING_STATUS_FINISH;
    }
}
