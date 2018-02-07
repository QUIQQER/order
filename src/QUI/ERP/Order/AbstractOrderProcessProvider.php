<?php

/**
 * This file contains QUI\ERP\Order\AbstractOrderProcessProvider
 */

namespace QUI\ERP\Order;

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
     * @var int
     */
    protected $currentStatus = self::PROCESSING_STATUS_START;

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
     * The processing order provider can display a separate step in the order processing
     *
     * @param AbstractOrder $Order
     * @return string
     */
    public function getDisplay(AbstractOrder $Order)
    {
        return '';
    }

    /**
     * Can be overwritten
     *
     * @param AbstractOrder $Order
     * @return int - Processing status
     */
    public function onOrderStart(AbstractOrder $Order)
    {
        $this->currentStatus = self::PROCESSING_STATUS_FINISH;

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
        $this->currentStatus = self::PROCESSING_STATUS_FINISH;

        return self::PROCESSING_STATUS_FINISH;
    }

    /**
     * Can be overwritten
     * Can throw Exception
     *
     * @param AbstractOrder $Order
     * @return integer
     */
    public function onOrderAbort(AbstractOrder $Order)
    {
        $this->currentStatus = self::PROCESSING_STATUS_FINISH;

        return self::PROCESSING_STATUS_FINISH;
    }
}
