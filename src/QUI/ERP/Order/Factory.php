<?php

/**
 * This file contains QUI\ERP\Order\Factory
 */

namespace QUI\ERP\Order;

use QUI;

use function date;

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
     * @param int|null $id (optional) - Fixed ID for the new order. Throws an exception if the ID is already taken.
     * @return Order
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\ERP\Order\Exception
     */
    public function create($PermissionUser = null, $hash = false, ?int $id = null)
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        QUI\Permissions\Permission::hasPermission(
            'quiqqer.order.edit',
            $PermissionUser
        );

        $Orders = Handler::getInstance();

        if ($id) {
            // Check if ID already exists
            try {
                $Orders->getOrderById($id);

                throw new Exception([
                    'quiqqer/order',
                    'exception.Factory.order_id_already_exists',
                    [
                        'id' => $id
                    ]
                ], $Orders::ERROR_ORDER_ID_ALREADY_EXISTS);
            } catch (\Exception $Exception) {
                if ($Exception->getCode() !== $Orders::ERROR_ORDER_NOT_FOUND) {
                    QUI\System\Log::writeException($Exception);
                    throw $Exception;
                }
            }
        }

        if ($hash === false) {
            $hash = QUI\Utils\Uuid::get();
        }

        $User = QUI::getUserBySession();
        $table = $Orders->table();
        $status = QUI\ERP\Constants::ORDER_STATUS_CREATED;

        if (Settings::getInstance()->get('orderStatus', 'standard')) {
            $status = (int)Settings::getInstance()->get('orderStatus', 'standard');
        }

        $orderData = [
            'id_prefix' => QUI\ERP\Order\Utils\Utils::getOrderPrefix(),
            'c_user' => $User->getId() ? $User->getId() : 0,
            'c_date' => date('Y-m-d H:i:s'),
            'hash' => $hash,
            'status' => $status,
            'customerId' => 0,
            'paid_status' => QUI\ERP\Constants::PAYMENT_STATUS_OPEN,
            'successful' => 0
        ];

        if ($id) {
            $orderData['id'] = $id;
        }

        QUI::getDataBase()->insert($table, $orderData);

        $orderId = QUI::getDataBase()->getPDO()->lastInsertId();
        $Order = $Orders->get($orderId);

        try {
            QUI::getEvents()->fireEvent('onQuiqqerOrderFactoryCreate', [$Order]);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());
        }

        return $Order;
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

        $orderId = $this->createOrderInProcessDataBaseEntry($PermissionUser);

        return Handler::getInstance()->getOrderInProcess($orderId);
    }

    /**
     * Create a new OrderInProcess database entry
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional, permission user, default = session user
     * @return int - OrderInProcess ID
     * @throws QUI\Database\Exception
     */
    public function createOrderInProcessDataBaseEntry($PermissionUser = null)
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        QUI\Permissions\Permission::hasPermission(
            'quiqqer.order.edit',
            $PermissionUser
        );

        $User = QUI::getUserBySession();
        $Orders = Handler::getInstance();
        $table = $Orders->tableOrderProcess();

        // @todo set default from customer

        $status = QUI\ERP\Constants::ORDER_STATUS_CREATED;

        if (Settings::getInstance()->get('orderStatus', 'standard')) {
            $status = (int)Settings::getInstance()->get('orderStatus', 'standard');
        }

        QUI::getDataBase()->insert($table, [
            'id_prefix' => QUI\ERP\Order\Utils\Utils::getOrderPrefix(),
            'c_user' => $User->getId(),
            'c_date' => date('Y-m-d H:i:s'),
            'hash' => QUI\Utils\Uuid::get(),
            'customerId' => $User->getId(),
            'status' => $status,
            'paid_status' => QUI\ERP\Constants::PAYMENT_STATUS_OPEN,
            'successful' => 0
        ]);

        return QUI::getDataBase()->getPDO()->lastInsertId();
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
