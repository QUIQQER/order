<?php

/**
 * This file contains QUI\ERP\Order\Factory
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\Utils\Singleton;

/**
 * Class Factory
 * Creates Orders
 *
 * @package QUI\ERP\Order
 */
class Handler extends Singleton
{
    /**
     * Return the order table
     *
     * @return string
     */
    public function table()
    {
        return QUI::getDBTableName('orders');
    }

    /**
     * Return a specific Order
     *
     * @param $orderId
     * @return Order
     */
    public function get($orderId)
    {
        return new Order($orderId);
    }

    /**
     * Return the data of a wanted order
     *
     * @param string|integer $orderId
     * @return array
     *
     * @throws QUI\Erp\Order\Exception
     */
    public function getOrderData($orderId)
    {
        $result = QUI::getDataBase()->fetch(array(
            'from'  => $this->table(),
            'where' => array(
                'id' => $orderId
            ),
            'limit' => 1
        ));

        if (!isset($result[0])) {
            throw new QUI\Erp\Order\Exception(
                'Order not found',
                404
            );
        }

        return $result[0];
    }

    /**
     *
     */
    public function search()
    {

    }
}
