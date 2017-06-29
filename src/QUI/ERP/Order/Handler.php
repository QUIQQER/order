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
    const EMPTY_VALUE = '---';

    //region Order

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

        if (!isset($result[0])) { // #locale
            throw new QUI\Erp\Order\Exception(
                'Order not found',
                404
            );
        }

        return $result[0];
    }

    //endregion

    //region Order Process

    /**
     * Return the order process table
     *
     * @return string
     */
    public function tableOrderProcess()
    {
        return QUI::getDBTableName('orderProcess');
    }

    /**
     * Return a Order which is in processing
     *
     * @param $orderId
     * @return OrderProcess
     */
    public function getOrderInProcess($orderId)
    {
        return new OrderProcess($orderId);
    }

    /**
     * Return all orders in process from a user
     *
     * @param QUI\Interfaces\Users\User $User
     * @return array
     */
    public function getOrdersInProcessFromUser(QUI\Interfaces\Users\User $User)
    {
        $result = array();

        $list = QUI::getDataBase()->fetch(array(
            'from'  => $this->table(),
            'where' => array(
                'customerId' => $User->getId()
            )
        ));

        foreach ($list as $entry) {
            try {
                $result[] = $this->getOrderInProcess($entry['id']);
            } catch (QUI\Erp\Order\Exception $Exception) {
            }
        }

        return $result;
    }

    /**
     * Return the last order in process from a user
     *
     * @param QUI\Interfaces\Users\User $User
     * @return OrderProcess
     * @throws QUI\Erp\Order\Exception
     */
    public function getLastOrderInProcessFromUser(QUI\Interfaces\Users\User $User)
    {
        $result = QUI::getDataBase()->fetch(array(
            'from'  => $this->table(),
            'where' => array(
                'customerId' => $User->getId()
            ),
            'limit' => 1,
            'order' => 'c_date DESC'
        ));

        if (!isset($result[0])) {
            throw new QUI\Erp\Order\Exception(
                'Order not found',
                404
            );
        }

        return $this->getOrderInProcess($result[0]['id']);
    }

    /**
     * Return the data of a wanted order
     *
     * @param string|integer $orderId
     * @return array
     *
     * @throws QUI\Erp\Order\Exception
     */
    public function getOrderProcessData($orderId)
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

    //endregion
}
