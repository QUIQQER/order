<?php

/**
 * This file contains QUI\ERP\Order\Factory
 */

namespace QUI\ERP\Order;

use QUI;

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
     * @param string|bool $hash - optional
     * @return Order
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\ERP\Order\Exception
     */
    public function create($PermissionUser = null, $hash = false)
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        QUI\Permissions\Permission::hasPermission(
            'quiqqer.order.edit',
            $PermissionUser
        );

        if ($hash === false) {
            $hash = QUI\Utils\Uuid::get();
        }

        $User   = QUI::getUserBySession();
        $Orders = Handler::getInstance();
        $table  = $Orders->table();

        QUI::getDataBase()->insert($table, [
            'id_prefix'   => QUI\ERP\Order\Utils\Utils::getOrderPrefix(),
            'c_user'      => $User->getId() ? $User->getId() : 0,
            'c_date'      => \date('Y-m-d H:i:s'),
            'hash'        => $hash,
            'status'      => AbstractOrder::STATUS_CREATED,
            'customerId'  => 0,
            'paid_status' => AbstractOrder::PAYMENT_STATUS_OPEN,
            'successful'  => 0
        ]);

        $orderId = QUI::getDataBase()->getPDO()->lastInsertId();

        return $Orders->get($orderId);
    }

    /**
     * Use createOrderInProcess()
     *
     * @param null $PermissionUser
     * @return OrderInProcess
     * @throws Exception
     * @throws QUI\Exception
     *
     * @deprecated
     */
    public function createOrderProcess($PermissionUser = null)
    {
        return $this->createOrderInProcess($PermissionUser);
    }

    /**
     * Creates a new order in processing
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional, permission user, default = session user
     * @return OrderInProcess
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\ERP\Order\Exception
     */
    public function createOrderInProcess($PermissionUser = null)
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

        QUI::getDataBase()->insert($table, [
            'id_prefix'   => QUI\ERP\Order\Utils\Utils::getOrderPrefix(),
            'c_user'      => $User->getId(),
            'c_date'      => \date('Y-m-d H:i:s'),
            'hash'        => QUI\Utils\Uuid::get(),
            'customerId'  => $User->getId(),
            'status'      => AbstractOrder::STATUS_CREATED,
            'paid_status' => AbstractOrder::PAYMENT_STATUS_OPEN,
            'successful'  => 0
        ]);

        $orderId = QUI::getDataBase()->getPDO()->lastInsertId();

        return $Orders->getOrderInProcess($orderId);
    }

    /**
     * Create a new Basket for the user
     *
     * @param null $User
     * @return QUI\ERP\Order\Basket\Basket
     *
     * @throws QUI\ERP\Order\Basket\Exception
     */
    public function createBasket($User = null)
    {
        if ($User === null) {
            $User = QUI::getUserBySession();
        }

        QUI::getDataBase()->insert(
            Handler::getInstance()->tableBasket(),
            ['uid' => $User->getId()]
        );

        $lastId = QUI::getDataBase()->getPDO()->lastInsertId();

        return new Basket\Basket($lastId, $User);
    }

    /**
     * Return the needles for an order construct
     *
     * @return array
     */
    public function getOrderConstructNeedles()
    {
        return [
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
        ];
    }
}
