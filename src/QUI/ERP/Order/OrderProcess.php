<?php

/**
 * This file contains QUI\ERP\Order\OrderProcess
 */

namespace QUI\ERP\Order;

use QUI;

/**
 * Class OrderProcess
 *
 * @package QUI\ERP\Order
 */
class OrderProcess extends AbstractOrder
{
    /**
     * Order constructor.
     *
     * @param string|integer $orderId - Order-ID
     */
    public function __construct($orderId)
    {
        parent::__construct(
            Handler::getInstance()->getOrderProcessData($orderId)
        );
    }


    public function update($PermissionUser = null)
    {
        // TODO: Implement update() method.
    }
}
