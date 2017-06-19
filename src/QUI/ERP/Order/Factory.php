<?php

/**
 * This file contains QUI\ERP\Order\Factory
 */

namespace QUI\ERP\Order;

use QUI;
use Ramsey\Uuid\Uuid;

/**
 * Class Factory
 * Creates Orders
 *
 * @package QUI\ERP\Order
 */
class Factory extends QUI\Utils\Singleton
{
    /**
     * Creates a new order
     *
     * @return Order
     * @todo permissions
     */
    public function create()
    {
        $User   = QUI::getUserBySession();
        $Orders = Handler::getInstance();
        $table  = $Orders->table();

        QUI::getDataBase()->insert($table, array(
            'c_user' => $User->getId(),
            'c_date' => time(),
            'hash'   => Uuid::uuid1()->toString()
        ));

        $orderId = QUI::getDataBase()->getPDO()->lastInsertId();

        return $Orders->get($orderId);
    }

    /**
     * Creates a new order in processing
     *
     * @return OrderProcess
     * @todo permissions
     */
    public function createOrderProcess()
    {
        $User   = QUI::getUserBySession();
        $Orders = Handler::getInstance();
        $table  = $Orders->table();

        QUI::getDataBase()->insert($table, array(
            'c_user' => $User->getId(),
            'c_date' => time(),
            'hash'   => Uuid::uuid1()->toString()
        ));

        $orderId = QUI::getDataBase()->getPDO()->lastInsertId();

        return $Orders->getOrderInProcess($orderId);
    }

    /**
     * Return the needles for an order construct
     *
     * @return array
     */
    public function getOrderConstructNeedles()
    {
        return array(
            'id',
            'uid',
            'status',
            'user',
            'address',
            'articles',
            'data',

            'payment_method',
            'payment_data',
            'payment_time',
            'payment_address',

            'hash',
            'c_date',
            'c_user'
    }
}
