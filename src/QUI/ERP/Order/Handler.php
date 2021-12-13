<?php

/**
 * This file contains QUI\ERP\Order\Handler
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\Utils\Singleton;

/**
 * Class Handler
 * - Handles orders and order in process
 *
 * @package QUI\ERP\Order
 */
class Handler extends Singleton
{
    const ERROR_ORDER_NOT_FOUND = 604; // a specific order wasn't found
    const ERROR_NO_ORDERS_FOUND = 605; // Search or last orders don't get results

    /**
     * Default empty value (placeholder for empty values)
     */
    const EMPTY_VALUE = '---';

    /**
     * @var array
     */
    protected $cache = [];

    /**
     * @var array
     */
    protected $orders = [];

    /**
     * Return all order process Provider
     *
     * @return array
     */
    public function getOrderProcessProvider()
    {
        $cacheProvider = 'package/quiqqer/order/providerOrderProcess';

        try {
            $providers = QUI\Cache\Manager::get($cacheProvider);
        } catch (QUI\Cache\Exception $Exception) {
            $packages = array_map(function ($package) {
                return $package['name'];
            }, QUI::getPackageManager()->getInstalled());

            $providers = [];

            foreach ($packages as $package) {
                try {
                    $Package = QUI::getPackage($package);

                    if ($Package->isQuiqqerPackage()) {
                        $providers = \array_merge($providers, $Package->getProvider('orderProcess'));
                    }
                } catch (QUI\Exception $Exception) {
                }
            }

            try {
                QUI\Cache\Manager::set($cacheProvider, $providers);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        // filter provider
        $result = [];

        foreach ($providers as $provider) {
            if (!\class_exists($provider)) {
                continue;
            }

            $Provider = new $provider();

            if (!($Provider instanceof AbstractOrderProcessProvider)) {
                continue;
            }

            $result[] = $Provider;
        }

        return $result;
    }

    /**
     * Remove a order instance
     *
     * @param $orderId
     */
    public function removeFromInstanceCache($orderId)
    {
        if (isset($this->orders[$orderId])) {
            unset($this->orders[$orderId]);
        }

        if (isset($this->cache[$orderId])) {
            unset($this->cache[$orderId]);
        }
    }

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
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     */
    public function get($orderId)
    {
        if (!isset($this->orders[$orderId])) {
            $this->orders[$orderId] = new Order($orderId);
        }

        return $this->orders[$orderId];
    }

    /**
     * Return the specific order via its hash
     * If a order exists with the hash, this will be returned
     * An order has higher priority as an order in process
     *
     * @param string $hash - Order Hash
     * @return Order|OrderInProcess|Order|Order
     *
     * @throws QUI\Exception
     * @throws Exception
     */
    public function getOrderByHash($hash)
    {
        $result = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from'   => $this->table(),
            'where'  => [
                'hash' => $hash
            ],
            'limit'  => 1
        ]);

        if (isset($result[0])) {
            return $this->get($result[0]['id']);
        }

        $result = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from'   => $this->tableOrderProcess(),
            'where'  => [
                'hash' => $hash
            ],
            'limit'  => 1
        ]);

        if (!isset($result[0])) {
            throw new Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
                self::ERROR_ORDER_NOT_FOUND
            );
        }

        return $this->getOrderInProcess($result[0]['id']);
    }

    /**
     * Return an order via its global process id
     * If a order exists with the id, this will be returned
     * An order has higher priority as an order in process
     *
     * If you want to get all orders, use getOrdersByGlobalProcessId()
     *
     * @param string $id - Global process id
     * @return Order|OrderInProcess|Order|Order
     *
     * @throws QUI\Exception
     * @throws Exception
     */
    public function getOrderByGlobalProcessId($id)
    {
        $result = QUI::getDataBase()->fetch([
            'select'   => 'id',
            'from'     => $this->table(),
            'where_or' => [
                'hash'              => $id,
                'global_process_id' => $id
            ],
            'limit'    => 1
        ]);

        if (!isset($result[0])) {
            throw new Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
                self::ERROR_ORDER_NOT_FOUND
            );
        }

        return $this->get($result[0]['id']);
    }

    /**
     * Return all orders via its global process id
     *
     * @param string $id - Global process id
     * @return Order[]|OrderInProcess|Order|Order[]
     *
     * @throws QUI\Database\Exception
     */
    public function getOrdersByGlobalProcessId($id)
    {
        $dbData = QUI::getDataBase()->fetch([
            'select'   => 'id',
            'from'     => $this->table(),
            'where_or' => [
                'hash'              => $id,
                'global_process_id' => $id
            ]
        ]);

        $result = [];

        if (!isset($result[0])) {
            return $result;
        }

        foreach ($dbData as $entry) {
            try {
                $result[] = $this->get($entry['id']);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        return $result;
    }

    /**
     * Return the specific order via its id
     * If a order exists with the hash, this will be returned
     * An order has higher priority as an order in process
     *
     * @param string $id - Order Id
     * @return Order|OrderInProcess
     *
     * @throws QUI\Exception
     * @throws Exception
     */
    public function getOrderById($id)
    {
        $result = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from'   => $this->table(),
            'where'  => [
                'id' => $id
            ],
            'limit'  => 1
        ]);

        if (isset($result[0])) {
            return $this->get($result[0]['id']);
        }

        $result = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from'   => $this->tableOrderProcess(),
            'where'  => [
                'id' => $id
            ],
            'limit'  => 1
        ]);

        if (!isset($result[0])) {
            throw new Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
                self::ERROR_ORDER_NOT_FOUND
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
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Database\Exception
     */
    public function getOrderData($orderId)
    {
        if (!\is_numeric($orderId)) {
            throw new Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
                self::ERROR_ORDER_NOT_FOUND
            );
        }

        $result = QUI::getDataBase()->fetch([
            'from'  => $this->table(),
            'where' => [
                'id' => $orderId
            ],
            'limit' => 1
        ]);

        if (!isset($result[0])) {
            throw new Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
                self::ERROR_ORDER_NOT_FOUND
            );
        }

        return $result[0];
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @param array $params
     *
     * @return Order[]
     */
    public function getOrdersByUser(QUI\Interfaces\Users\User $User, $params = [])
    {
        $query = [
            'select' => ['id', 'customerId'],
            'from'   => $this->table(),
            'where'  => [
                'customerId' => $User->getId()
            ]
        ];

        if (isset($params['order'])) {
            switch ($params['order']) {
                case 'id':
                case 'id ASC':
                case 'id DESC':
                case 'status':
                case 'status ASC':
                case 'status DESC':
                case 'c_date':
                case 'c_date ASC':
                case 'c_date DESC':
                case 'paid_date':
                case 'paid_date ASC':
                case 'paid_date DESC':
                    $query['order'] = $params['order'];
            }
        }

        if (isset($params['limit'])) {
            $query['limit'] = $params['limit'];
        }

        try {
            $data = QUI::getDataBase()->fetch($query);
        } catch (QUI\Exception $Exception) {
            return [];
        }

        $result = [];

        foreach ($data as $entry) {
            try {
                $result[] = new Order($entry['id']);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        return $result;
    }

    /**
     * Return the number of orders from the user
     *
     * @param QUI\Interfaces\Users\User $User
     * @return int
     *
     * @throws QUI\Database\Exception
     */
    public function countOrdersByUser(QUI\Interfaces\Users\User $User)
    {
        $data = QUI::getDataBase()->fetch([
            'count'  => 'id',
            'select' => 'id',
            'from'   => $this->table(),
            'where'  => [
                'customerId' => $User->getId()
            ]
        ]);

        if (isset($data[0]['id'])) {
            return (int)$data[0]['id'];
        }

        return 0;
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
        return QUI::getDBTableName('orders_process');
    }

    /**
     * Return a Order which is in processing
     *
     * @param $orderId
     * @return OrderInProcess
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\ERP\Exception
     * @throws  QUI\Database\Exception
     */
    public function getOrderInProcess($orderId)
    {
        if (!isset($this->cache[$orderId])) {
            $this->cache[$orderId] = new OrderInProcess($orderId);
        }

        return $this->cache[$orderId];
    }

    /**
     * Return a Order which is in processing
     *
     * @param string $hash - hash of the order
     * @return OrderInProcess
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\ERP\Exception
     * @throws QUI\Database\Exception
     */
    public function getOrderInProcessByHash($hash)
    {
        $result = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from'   => $this->tableOrderProcess(),
            'where'  => [
                'hash' => $hash
            ],
            'limit'  => 1
        ]);

        if (!isset($result[0])) {
            throw new Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
                self::ERROR_ORDER_NOT_FOUND
            );
        }

        return $this->getOrderInProcess($result[0]['id']);
    }

    /**
     * Return all orders in process from a user
     *
     * @param QUI\Interfaces\Users\User $User
     * @return array
     *
     * @throws QUI\Database\Exception
     */
    public function getOrdersInProcessFromUser(QUI\Interfaces\Users\User $User)
    {
        $result = [];

        $list = QUI::getDataBase()->fetch([
            'from'  => $this->tableOrderProcess(),
            'where' => [
                'customerId' => $User->getId()
            ]
        ]);

        foreach ($list as $entry) {
            try {
                $result[] = $this->getOrderInProcess($entry['id']);
            } catch (Exception $Exception) {
            } catch (QUI\ERP\Exception $Exception) {
            }
        }

        return $result;
    }

    /**
     * Return order in process number form a user
     *
     * @param QUI\Interfaces\Users\User $User
     * @return int
     *
     * @throws QUI\Database\Exception
     */
    public function countOrdersInProcessFromUser(QUI\Interfaces\Users\User $User)
    {
        $data = QUI::getDataBase()->fetch([
            'count'  => 'id',
            'select' => 'id',
            'from'   => $this->tableOrderProcess(),
            'where'  => [
                'customerId' => $User->getId()
            ]
        ]);

        if (isset($data[0]['id'])) {
            return (int)$data[0]['id'];
        }

        return 0;
    }

    /**
     * Return the last order in process from a user
     *
     * @param QUI\Interfaces\Users\User $User
     * @return OrderInProcess
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\ERP\Exception
     * @throws QUI\Database\Exception
     */
    public function getLastOrderInProcessFromUser(QUI\Interfaces\Users\User $User)
    {
        $result = QUI::getDataBase()->fetch([
            'from'  => $this->tableOrderProcess(),
            'where' => [
                'customerId' => $User->getId(),
                'successful' => 0
            ],
            'limit' => 1,
            'order' => 'c_date DESC'
        ]);

        if (!isset($result[0])) {
            throw new Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.no.orders.found'),
                self::ERROR_NO_ORDERS_FOUND
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
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Database\Exception
     */
    public function getOrderProcessData($orderId)
    {
        $result = QUI::getDataBase()->fetch([
            'from'  => $this->tableOrderProcess(),
            'where' => [
                'id' => $orderId
            ],
            'limit' => 1
        ]);

        if (!isset($result[0])) {
            throw new Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
                self::ERROR_ORDER_NOT_FOUND
            );
        }

        return $result[0];
    }

    //endregion

    //region basket

    /**
     * Return the table for baskets
     *
     * @return string
     */
    public function tableBasket()
    {
        return QUI::getDBTableName('baskets');
    }

    /**
     * Return a basket by its string
     * Can be a basket id or a basket hash
     *
     * @param string|integer $str - hash or basket id
     * @param $User - optional, user of the basket
     *
     * @return QUI\ERP\Order\Basket\Basket
     *
     * @throws Basket\Exception
     * @throws Basket\ExceptionBasketNotFound
     * @throws QUI\Users\Exception
     * @throws QUI\Database\Exception
     */
    public function getBasket($str, $User = null)
    {
        if (\is_numeric($str)) {
            return self::getBasketById($str, $User);
        }

        return self::getBasketByHash($str, $User);
    }

    /**
     * @param int $basketId
     * @param $User - optional, user of the basket
     *
     * @return Basket\Basket
     *
     * @throws Basket\Exception
     * @throws Basket\ExceptionBasketNotFound
     * @throws QUI\Users\Exception
     * @throws QUI\Database\Exception
     */
    public function getBasketById($basketId, $User = null)
    {
        $data = QUI::getDataBase()->fetch([
            'from'  => QUI\ERP\Order\Handler::getInstance()->tableBasket(),
            'where' => [
                'id' => $basketId
            ],
            'limit' => 1
        ]);

        if (!isset($data[0])) {
            throw new Basket\ExceptionBasketNotFound([
                'quiqqer/order',
                'exception.basket.not.found'
            ]);
        }

        if ($User === null) {
            $User = QUI::getUserBySession();
        } else {
            $basketData = $data[0];
            $User       = QUI::getUsers()->get($basketData['uid']);
        }

        $this->checkBasketPermissions($User);

        return new Basket\Basket($data[0]['id'], $User);
    }

    /**
     * @param string $hash
     * @param $User - optional, user of the basket
     *
     * @return Basket\Basket
     *
     * @throws Basket\Exception
     * @throws Basket\ExceptionBasketNotFound
     * @throws QUI\Users\Exception
     * @throws QUI\Database\Exception
     */
    public function getBasketByHash($hash, $User = null)
    {
        $data = QUI::getDataBase()->fetch([
            'from'  => QUI\ERP\Order\Handler::getInstance()->tableBasket(),
            'where' => [
                'hash' => $hash
            ],
            'limit' => 1
        ]);

        if (!isset($data[0])) {
            throw new Basket\ExceptionBasketNotFound([
                'quiqqer/order',
                'exception.basket.not.found'
            ]);
        }


        if ($User === null) {
            $User = QUI::getUserBySession();
        } else {
            $basketData = $data[0];
            $User       = QUI::getUsers()->get($basketData['uid']);
        }

        $this->checkBasketPermissions($User);

        return new Basket\Basket($data[0]['id'], $User);
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @return QUI\ERP\Order\Basket\Basket
     *
     * @throws Basket\Exception
     * @throws Basket\ExceptionBasketNotFound
     * @throws QUI\Database\Exception
     */
    public function getBasketFromUser(QUI\Interfaces\Users\User $User)
    {
        $this->checkBasketPermissions($User);

        $data = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from'   => QUI\ERP\Order\Handler::getInstance()->tableBasket(),
            'where'  => [
                'uid' => $User->getId()
            ],
            'limit'  => 1
        ]);


        if (!isset($data[0])) {
            throw new Basket\ExceptionBasketNotFound([
                'quiqqer/order',
                'exception.basket.not.found'
            ]);
        }

        $Basket = new Basket\Basket($data[0]['id'], $User);

        return $Basket;
    }

    /**
     * Return the data from a basket
     *
     * @param string|integer $basketId
     * @param null|QUI\Interfaces\Users\User $User
     * @return array
     *
     * @throws Basket\Exception
     * @throws Basket\ExceptionBasketNotFound
     * @throws QUI\Database\Exception
     */
    public function getBasketData($basketId, $User = null)
    {
        if ($User === null) {
            $User = QUI::getUserBySession();
        }

        $this->checkBasketPermissions($User);

        $data = QUI::getDataBase()->fetch([
            'from'  => QUI\ERP\Order\Handler::getInstance()->tableBasket(),
            'where' => [
                'id'  => (int)$basketId,
                'uid' => $User->getId()
            ],
            'limit' => 1
        ]);

        if (!isset($data[0])) {
            throw new Basket\ExceptionBasketNotFound([
                'quiqqer/order',
                'exception.basket.not.found'
            ]);
        }

        return $data[0];
    }

    /**
     * Basket permission check
     *
     * @param QUI\Interfaces\Users\User $User
     * @throws Basket\Exception
     */
    protected function checkBasketPermissions(QUI\Interfaces\Users\User $User)
    {
        $hasPermissions = function () use ($User) {
            if (QUI::getUserBySession()->isSU()) {
                return true;
            }

            if (QUI::getUsers()->isSystemUser(QUI::getUserBySession())) {
                return true;
            }

            if ($User->getId() === QUI::getUserBySession()->getId()) {
                return true;
            }

            return false;
        };

        if ($hasPermissions() === false) {
            throw new Basket\Exception([
                'quiqqer/order',
                'exception.basket.no.permissions'
            ]);
        }
    }

    //endregion
}
