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
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional, permission user, default = session user
     * @return Order
     */
    public function create($PermissionUser = null)
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        QUI\Permissions\Permission::hasPermission(
            'quiqqer.order.edit',
            $PermissionUser
        );

        $User   = QUI::getUserBySession();
        $Orders = Handler::getInstance();
        $table  = $Orders->table();

        QUI::getDataBase()->insert($table, array(
            'c_user' => $User->getId(),
            'c_date' => date('Y-m-d H:i:s'),
            'hash'   => Uuid::uuid1()->toString()
        ));

        $orderId = QUI::getDataBase()->getPDO()->lastInsertId();

        return $Orders->get($orderId);
    }

    /**
     * Creates a new order in processing
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional, permission user, default = session user
     * @return OrderProcess
     */
    public function createOrderProcess($PermissionUser = null)
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        QUI\Permissions\Permission::hasPermission(
            'quiqqer.order.edit',
            $PermissionUser
        );

        $User   = QUI::getUserBySession();
        $Orders = Handler::getInstance();
        $table  = $Orders->tableOrderProcess();

        // @todo set default from customer

        QUI::getDataBase()->insert($table, array(
            'c_user'     => $User->getId(),
            'c_date'     => date('Y-m-d H:i:s'),
            'hash'       => Uuid::uuid1()->toString(),
            'customerId' => $User->getId()
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
            'status',
            'customerId',
            'customer',
            'addressInvoice',
            'addressInvoice',
            'addressDelivery',
            'data',

            'payment_method',
            'payment_data',
            'payment_time',
            'payment_address',

            'hash',
            'c_date',
            'c_user'
        );
    }
}
