<?php

/**
 * This file contains QUI\ERP\Order\Handler
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\ERP\Customer\Utils as CustomerUtils;
use QUI\Utils\Singleton;

use function array_merge;
use function class_exists;
use function is_numeric;
use function strtotime;
use function trim;

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
    const ERROR_ORDER_ID_ALREADY_EXISTS = 606; // attempt to create a new order with an already existing id

    /**
     * Default empty value (placeholder for empty values)
     */
    const EMPTY_VALUE = '---';

    /**
     * @var array
     */
    protected array $cache = [];

    /**
     * @var array
     */
    protected array $orders = [];

    /**
     * Return all order process Provider
     *
     * @return array
     */
    public function getOrderProcessProvider(): array
    {
        $cacheProvider = 'package/quiqqer/order/providerOrderProcess';

        try {
            $providers = QUI\Cache\Manager::get($cacheProvider);
        } catch (QUI\Cache\Exception) {
            $packages = array_map(function ($package) {
                return $package['name'];
            }, QUI::getPackageManager()->getInstalled());

            $providers = [];

            foreach ($packages as $package) {
                try {
                    $Package = QUI::getPackage($package);

                    if ($Package->isQuiqqerPackage()) {
                        $providers = array_merge($providers, $Package->getProvider('orderProcess'));
                    }
                } catch (QUI\Exception) {
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
            if (!class_exists($provider)) {
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
     * Remove an order instance
     *
     * @param $orderId
     */
    public function removeFromInstanceCache($orderId): void
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
    public function table(): string
    {
        return QUI::getDBTableName('orders');
    }

    /**
     * Return a specific Order
     *
     * @param int|string $orderId
     * @return Order
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     */
    public function get(int|string $orderId): Order
    {
        if (!isset($this->orders[$orderId])) {
            $this->orders[$orderId] = new Order($orderId);
        }

        return $this->orders[$orderId];
    }

    /**
     * Return the specific order via its hash
     * If an order exists with the hash, this will be returned
     * An order has higher priority as an order in process
     *
     * @param string $hash - Order Hash
     * @return Order|OrderInProcess
     *
     * @throws QUI\Exception
     * @throws Exception
     */
    public function getOrderByHash(string $hash): OrderInProcess|Order
    {
        $result = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from' => $this->table(),
            'where' => [
                'hash' => $hash
            ],
            'limit' => 1
        ]);

        if (isset($result[0])) {
            return $this->get($result[0]['id']);
        }

        $result = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from' => $this->tableOrderProcess(),
            'where' => [
                'hash' => $hash
            ],
            'limit' => 1
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
     * If an order exists with the id, this will be returned
     * An order has higher priority as an order in process
     *
     * If you want to get all orders, use getOrdersByGlobalProcessId()
     *
     * @param string|int $id - Global process id
     * @return Order
     *
     * @throws QUI\Exception
     * @throws Exception
     */
    public function getOrderByGlobalProcessId(int|string $id): Order
    {
        $result = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from' => $this->table(),
            'where_or' => [
                'hash' => $id,
                'global_process_id' => $id
            ],
            'limit' => 1
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
     * @return Order[]
     *<
     * @throws QUI\Database\Exception
     */
    public function getOrdersByGlobalProcessId(string $id): array
    {
        $dbData = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from' => $this->table(),
            'where_or' => [
                'hash' => $id,
                'global_process_id' => $id
            ]
        ]);

        if (!count($dbData)) {
            return [];
        }

        $result = [];

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
     * If an order exists with the hash, this will be returned
     * An order has higher priority as an order in process
     *
     * @param int|string $id - Order Id
     * @return Order|OrderInProcess
     *
     * @throws QUI\Exception
     * @throws Exception
     */
    public function getOrderById(int|string $id): OrderInProcess|Order
    {
        $result = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from' => $this->table(),
            'where' => [
                'hash' => $id
            ],
            'limit' => 1
        ]);

        if (isset($result[0])) {
            return $this->get($result[0]['id']);
        }


        $result = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from' => $this->table(),
            'where' => [
                'id' => $id
            ],
            'limit' => 1
        ]);

        if (isset($result[0])) {
            return $this->get($result[0]['id']);
        }


        $result = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from' => $this->tableOrderProcess(),
            'where' => [
                'id' => $id
            ],
            'limit' => 1
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
     * @param integer|string $orderId
     * @return array
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Database\Exception
     */
    public function getOrderData(int|string $orderId): array
    {
        $result = QUI::getDataBase()->fetch([
            'from' => $this->table(),
            'where' => [
                'hash' => $orderId
            ],
            'limit' => 1
        ]);

        if (empty($result)) {
            $result = QUI::getDataBase()->fetch([
                'from' => $this->table(),
                'where' => [
                    'id' => $orderId
                ],
                'limit' => 1
            ]);
        }

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
    public function getOrdersByUser(QUI\Interfaces\Users\User $User, array $params = []): array
    {
        $query = [
            'select' => ['id', 'customerId'],
            'from' => $this->table(),
            'where' => [
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
        } catch (QUI\Exception) {
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
    public function countOrdersByUser(QUI\Interfaces\Users\User $User): int
    {
        $data = QUI::getDataBase()->fetch([
            'count' => 'id',
            'select' => 'id',
            'from' => $this->table(),
            'where' => [
                'customerId' => $User->getId()
            ]
        ]);

        if (isset($data[0]['id'])) {
            return (int)$data[0]['id'];
        }

        return 0;
    }

    /**
     * Sends email to an order customer with successful (full) payment info.
     *
     * @param AbstractOrder $Order
     * @return void
     */
    public function sendOrderPaymentSuccessMail(AbstractOrder $Order): void
    {
        $Customer = $Order->getCustomer();
        $CustomerLocale = $Customer->getLocale();

        $subject = $CustomerLocale->get(
            'quiqqer/order',
            'mail.payment_success.subject',
            $this->getLocaleVarsForOrderMail($Order)
        );

        $body = $CustomerLocale->get(
            'quiqqer/order',
            'mail.payment_success.body',
            $this->getLocaleVarsForOrderMail($Order)
        );

        $Mailer = QUI::getMailManager()->getMailer();

        $Mailer->setSubject($subject);
        $Mailer->setBody($body);

        $Mailer->addRecipient(CustomerUtils::getInstance()->getEmailByCustomer($Customer));

        try {
            $Mailer->send();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Get all placeholder variables for order mails.
     *
     * @param AbstractOrder $Order
     * @return array
     */
    protected function getLocaleVarsForOrderMail(AbstractOrder $Order): array
    {
        $Customer = $Order->getCustomer();
        $CustomerLocale = $Customer->getLocale();
        $CustomerAddress = $Customer->getAddress();
        $user = $CustomerAddress->getAttribute('contactPerson');

        if (empty($user)) {
            $user = $Customer->getName();
        }

        if (empty($user)) {
            $user = $Customer->getAddress()->getName();
        }

        $user = trim($user);

        // contact person
        $ContactPersonAddress = CustomerUtils::getInstance()->getContactPersonAddress($Customer);

        if ($ContactPersonAddress) {
            $contactPerson = $ContactPersonAddress->getName();
        }

        if (empty($contactPerson)) {
            $contactPerson = $user;
        }

        $contactPersonOrName = $contactPerson;

        if (empty($contactPersonOrName)) {
            $contactPersonOrName = $user;
        }

        // Customer
        $Address = $Order->getInvoiceAddress();

        // customer name
        $user = $Address->getAttribute('contactPerson');

        if (empty($user)) {
            $user = $Customer->getName();
        }

        if (empty($user)) {
            $user = $Address->getName();
        }

        $user = trim($user);

        // email
        $email = $Customer->getAttribute('email');

        if (empty($email)) {
            $mailList = $Address->getMailList();

            if (isset($mailList[0])) {
                $email = $mailList[0];
            }
        }

        // Customer company
        $customerCompany = $Address->getAttribute('company');
        $companyOrName = $customerCompany;

        if (empty($companyOrName)) {
            $companyOrName = $user;
        }

        // Shop company
        $company = '';

        try {
            $Conf = QUI::getPackage('quiqqer/erp')->getConfig();
            $company = $Conf->get('company', 'name');
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return [
            'orderId' => $Order->getPrefixedId(),
            'hash' => $Order->getUUID(),
            'date' => $CustomerLocale->formatDate(strtotime($Order->getCreateDate())),
            'systemCompany' => $company,

            'contactPerson' => $contactPerson,
            'contactPersonOrName' => $contactPersonOrName,

            'user' => $user,
            'name' => $user,
            'company' => $Address->getAttribute('company'),
            'companyOrName' => $companyOrName,
            'address' => $Address->render(),
            'email' => $email,
            'salutation' => $Address->getAttribute('salutation'),
            'firstname' => $Address->getAttribute('firstname'),
            'lastname' => $Address->getAttribute('lastname')
        ];
    }

    //endregion

    //region Order Process

    /**
     * Return the order process table
     *
     * @return string
     */
    public function tableOrderProcess(): string
    {
        return QUI::getDBTableName('orders_process');
    }

    /**
     * Return an Order which is in processing
     *
     * @param $orderId
     * @return OrderInProcess
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\ERP\Exception
     * @throws  QUI\Database\Exception
     */
    public function getOrderInProcess($orderId): OrderInProcess
    {
        if (!isset($this->cache[$orderId])) {
            $this->cache[$orderId] = new OrderInProcess($orderId);
        }

        return $this->cache[$orderId];
    }

    /**
     * Return an Order which is in processing
     *
     * @param string $hash - hash of the order
     * @return OrderInProcess
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\ERP\Exception
     * @throws QUI\Database\Exception
     */
    public function getOrderInProcessByHash(string $hash): OrderInProcess
    {
        $result = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from' => $this->tableOrderProcess(),
            'where' => [
                'hash' => $hash
            ],
            'limit' => 1
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
    public function getOrdersInProcessFromUser(QUI\Interfaces\Users\User $User): array
    {
        $result = [];

        $list = QUI::getDataBase()->fetch([
            'from' => $this->tableOrderProcess(),
            'where' => [
                'customerId' => $User->getId()
            ]
        ]);

        foreach ($list as $entry) {
            try {
                $result[] = $this->getOrderInProcess($entry['id']);
            } catch (Exception) {
            } catch (QUI\ERP\Exception) {
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
    public function countOrdersInProcessFromUser(QUI\Interfaces\Users\User $User): int
    {
        $data = QUI::getDataBase()->fetch([
            'count' => 'id',
            'select' => 'id',
            'from' => $this->tableOrderProcess(),
            'where' => [
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
    public function getLastOrderInProcessFromUser(QUI\Interfaces\Users\User $User): OrderInProcess
    {
        $result = QUI::getDataBase()->fetch([
            'from' => $this->tableOrderProcess(),
            'where' => [
                'customerId' => $User->getId(),
                'successful' => 0
            ],
            'limit' => 1,
            'order' => 'c_date DESC'
        ]);

        if (!isset($result[0])) {
            try {
                $result = QUI::getEvents()->fireEvent('orderProcessGetOrder');

                foreach ($result as $Order) {
                    if ($Order instanceof OrderInProcess) {
                        return $Order;
                    }
                }
            } catch (\Exception) {
            }

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
     * @param integer|string $orderId
     * @return array
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Database\Exception
     */
    public function getOrderProcessData(int|string $orderId): array
    {
        $result = QUI::getDataBase()->fetch([
            'from' => $this->tableOrderProcess(),
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
    public function tableBasket(): string
    {
        return QUI::getDBTableName('baskets');
    }

    /**
     * Return a basket by its string
     * Can be a basket id or a basket hash
     *
     * @param integer|string $str - hash or basket id
     * @param $User - optional, user of the basket
     *
     * @return QUI\ERP\Order\Basket\Basket
     *
     * @throws Basket\Exception
     * @throws Basket\ExceptionBasketNotFound
     * @throws QUI\Users\Exception
     * @throws QUI\Database\Exception
     */
    public function getBasket(int|string $str, $User = null): Basket\Basket
    {
        if (is_numeric($str)) {
            return self::getBasketById($str, $User);
        }

        return self::getBasketByHash($str, $User);
    }

    /**
     * @param int|string $basketId
     * @param $User - optional, user of the basket
     *
     * @return Basket\Basket
     *
     * @throws Basket\Exception
     * @throws Basket\ExceptionBasketNotFound
     * @throws QUI\Users\Exception
     * @throws QUI\Database\Exception
     */
    public function getBasketById(int|string $basketId, $User = null): Basket\Basket
    {
        $data = QUI::getDataBase()->fetch([
            'from' => QUI\ERP\Order\Handler::getInstance()->tableBasket(),
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
            $User = QUI::getUsers()->get($basketData['uid']);
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
    public function getBasketByHash(string $hash, $User = null): Basket\Basket
    {
        $data = QUI::getDataBase()->fetch([
            'from' => QUI\ERP\Order\Handler::getInstance()->tableBasket(),
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
            $User = QUI::getUsers()->get($basketData['uid']);
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
    public function getBasketFromUser(QUI\Interfaces\Users\User $User): Basket\Basket
    {
        $this->checkBasketPermissions($User);

        $data = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from' => QUI\ERP\Order\Handler::getInstance()->tableBasket(),
            'where' => [
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

        return new Basket\Basket($data[0]['id'], $User);
    }

    /**
     * Return the data from a basket
     *
     * @param integer|string $basketId
     * @param null|QUI\Interfaces\Users\User $User
     * @return array
     *
     * @throws Basket\Exception
     * @throws Basket\ExceptionBasketNotFound
     * @throws QUI\Database\Exception
     */
    public function getBasketData(int|string $basketId, QUI\Interfaces\Users\User $User = null): array
    {
        if ($User === null) {
            $User = QUI::getUserBySession();
        }

        $this->checkBasketPermissions($User);

        $data = QUI::getDataBase()->fetch([
            'from' => QUI\ERP\Order\Handler::getInstance()->tableBasket(),
            'where' => [
                'id' => (int)$basketId,
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
    protected function checkBasketPermissions(QUI\Interfaces\Users\User $User): void
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
