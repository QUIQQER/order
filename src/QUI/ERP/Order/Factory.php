<?php

/**
 * This file contains QUI\ERP\Order\Factory
 */

namespace QUI\ERP\Order;

/**
 * Class Factory
 * Creates Orders
 *
 * @package QUI\ERP\Order
 */
class Factory
{
    /**
     * Creates a new order
     *
     * @return Order
     */
    public static function create()
    {
        $table = Handler::getInstance()->table();
    }
}
