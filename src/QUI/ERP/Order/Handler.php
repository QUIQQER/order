<?php

/**
 * This file contains QUI\ERP\Order\Handler
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\ERP\Customer\Utils as CustomerUtils;
use QUI\ERP\Order\Basket\Basket;
use QUI\ERP\Order\Basket\Exception;
use QUI\ERP\Order\Basket\ExceptionBasketNotFound;
use QUI\ExceptionStack;
use QUI\Interfaces\Users\User;
use QUI\Utils\Doctrine;
use QUI\Utils\Singleton;

use function array_merge;
use function array_pad;
use function class_exists;
use function explode;
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
     * @var array<string, mixed>
     */
    protected array $cache = [];

    /**
     * Return all order process Provider
     *
     * @return list<AbstractOrderProcessProvider>
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
    public function get(int | string $orderId): Order
    {
        return new Order($orderId);
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
    public function getOrderByHash(string $hash): OrderInProcess | Order
    {
        $Connection = QUI::getDataBaseConnection();
        $orderId = $Connection->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier($this->table()))
            ->where(Doctrine::quoteIdentifier('hash') . ' = :hash')
            ->setParameter('hash', $hash)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if ($orderId !== false) {
            return $this->get($orderId);
        }

        $orderId = $Connection->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier($this->tableOrderProcess()))
            ->where(Doctrine::quoteIdentifier('hash') . ' = :hash')
            ->setParameter('hash', $hash)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if ($orderId === false) {
            throw new QUI\ERP\Order\Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
                self::ERROR_ORDER_NOT_FOUND
            );
        }

        return $this->getOrderInProcess($orderId);
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
    public function getOrderByGlobalProcessId(int | string $id): Order
    {
        $QueryBuilder = QUI::getDataBaseConnection()->createQueryBuilder();
        $orderId = $QueryBuilder
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier($this->table()))
            ->where($QueryBuilder->expr()->or(
                Doctrine::quoteIdentifier('hash') . ' = :id',
                Doctrine::quoteIdentifier('global_process_id') . ' = :id'
            ))
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if ($orderId === false) {
            throw new QUI\ERP\Order\Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
                self::ERROR_ORDER_NOT_FOUND
            );
        }

        return $this->get($orderId);
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
        $QueryBuilder = QUI::getDataBaseConnection()->createQueryBuilder();
        $orderIds = $QueryBuilder
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier($this->table()))
            ->where($QueryBuilder->expr()->or(
                Doctrine::quoteIdentifier('hash') . ' = :id',
                Doctrine::quoteIdentifier('global_process_id') . ' = :id'
            ))
            ->setParameter('id', $id)
            ->executeQuery()
            ->fetchFirstColumn();

        if (empty($orderIds)) {
            return [];
        }

        $result = [];

        foreach ($orderIds as $orderId) {
            try {
                $result[] = $this->get($orderId);
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
    public function getOrderById(int | string $id): OrderInProcess | Order
    {
        $Connection = QUI::getDataBaseConnection();
        $orderId = $Connection->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier($this->table()))
            ->where(Doctrine::quoteIdentifier('hash') . ' = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if ($orderId !== false) {
            return $this->get($orderId);
        }

        $orderId = $Connection->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier($this->table()))
            ->where(Doctrine::quoteIdentifier('id') . ' = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if ($orderId !== false) {
            return $this->get($orderId);
        }

        $orderId = $Connection->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier($this->tableOrderProcess()))
            ->where(Doctrine::quoteIdentifier('id') . ' = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if ($orderId === false) {
            throw new QUI\ERP\Order\Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
                self::ERROR_ORDER_NOT_FOUND
            );
        }

        return $this->getOrderInProcess($orderId);
    }

    /**
     * Return the data of a wanted order
     *
     * @param integer|string $orderId
     * @return array<string, mixed>
     *
     * @throws QUI\Database\Exception|QUI\ERP\Order\Exception
     */
    public function getOrderData(int | string $orderId): array
    {
        $Connection = QUI::getDataBaseConnection();
        $result = $Connection->createQueryBuilder()
            ->select('*')
            ->from(Doctrine::quoteIdentifier($this->table()))
            ->where(Doctrine::quoteIdentifier('hash') . ' = :orderId')
            ->setParameter('orderId', $orderId)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if ($result === false) {
            $result = $Connection->createQueryBuilder()
                ->select('*')
                ->from(Doctrine::quoteIdentifier($this->table()))
                ->where(Doctrine::quoteIdentifier('id') . ' = :orderId')
                ->setParameter('orderId', $orderId)
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchAssociative();
        }

        if ($result === false) {
            throw new QUI\ERP\Order\Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
                self::ERROR_ORDER_NOT_FOUND
            );
        }

        return $result;
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @param array<string, mixed> $params
     *
     * @return Order[]
     */
    public function getOrdersByUser(QUI\Interfaces\Users\User $User, array $params = []): array
    {
        $QueryBuilder = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select(
                Doctrine::quoteIdentifier('id'),
                Doctrine::quoteIdentifier('customerId'),
                Doctrine::quoteIdentifier('hash')
            )
            ->from(Doctrine::quoteIdentifier($this->table()))
            ->where(Doctrine::quoteIdentifier('customerId') . ' = :customerId')
            ->setParameter('customerId', $User->getUUID());

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
                    [$orderField, $orderDirection] = array_pad(
                        explode(' ', $params['order'], 2),
                        2,
                        'ASC'
                    );
                    $QueryBuilder->orderBy(Doctrine::quoteIdentifier($orderField), $orderDirection);
            }
        }

        if (isset($params['limit'])) {
            $limit = explode(',', (string)$params['limit'], 2);

            if (isset($limit[1])) {
                $QueryBuilder->setFirstResult((int)$limit[0]);
                $QueryBuilder->setMaxResults((int)$limit[1]);
            } else {
                $QueryBuilder->setMaxResults((int)$limit[0]);
            }
        }

        try {
            $data = $QueryBuilder->executeQuery()->fetchAllAssociative();
        } catch (\Exception) {
            return [];
        }

        $result = [];

        foreach ($data as $entry) {
            try {
                $result[] = new Order($entry['hash']);
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
        return (int)QUI::getDataBaseConnection()->createQueryBuilder()
            ->select('COUNT(' . Doctrine::quoteIdentifier('id') . ')')
            ->from(Doctrine::quoteIdentifier($this->table()))
            ->where(Doctrine::quoteIdentifier('customerId') . ' = :customerId')
            ->setParameter('customerId', $User->getUUID())
            ->executeQuery()
            ->fetchOne();
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

        $mailerAttributes = [];
        $Project = $this->getProjectForCustomerMail($Order);

        if ($Project) {
            $mailerAttributes['Project'] = $Project;
        }

        $Mailer = QUI::getMailManager()->getMailer($mailerAttributes);

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
     * @return array<string, mixed>
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
        $Conf = QUI::getPackage('quiqqer/erp')->getConfig();

        if ($Conf === null) {
            throw new QUI\Exception('The quiqqer/erp package configuration is not available.');
        }

        try {
            $company = $Conf->get('company', 'name');
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return [
            'orderId' => $Order->getPrefixedNumber(),
            'hash' => $Order->getUUID(),
            'date' => $CustomerLocale->formatDate($Order->getCreateDate()),
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

    /**
     * Resolve the best matching project for customer-facing order mails.
     */
    protected function getProjectForCustomerMail(AbstractOrder $Order): null | QUI\Projects\Project
    {
        $projectName = $Order->getAttribute('project_name') ?: false;
        $customerLang = false;

        try {
            $Customer = $Order->getCustomer();
            $customerLang = $Customer->getLang() ?: false;
        } catch (\Exception) {
        }

        if ($projectName) {
            try {
                $Project = QUI::getRewrite()->getProject();

                if (!$Project || $Project->getName() !== $projectName) {
                    $Project = QUI::getProjectManager()->getProject($projectName);
                }

                if ($customerLang && in_array($customerLang, $Project->getLanguages(), true)) {
                    return QUI::getProjectManager()->getProject($projectName, $customerLang);
                }

                return $Project;
            } catch (\Exception) {
            }
        }

        return QUI::getRewrite()->getProject() ?: null;
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
     * @param int|string $orderId
     * @return OrderInProcess
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\ERP\Exception
     * @throws  QUI\Database\Exception
     */
    public function getOrderInProcess($orderId): OrderInProcess
    {
        return new OrderInProcess($orderId);
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
        $orderId = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier($this->tableOrderProcess()))
            ->where(Doctrine::quoteIdentifier('hash') . ' = :hash')
            ->setParameter('hash', $hash)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if ($orderId === false) {
            throw new QUI\ERP\Order\Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
                self::ERROR_ORDER_NOT_FOUND
            );
        }

        return $this->getOrderInProcess($orderId);
    }

    /**
     * Return all orders in process from a user
     *
     * @param QUI\Interfaces\Users\User $User
     * @return list<OrderInProcess>
     *
     * @throws QUI\Database\Exception
     */
    public function getOrdersInProcessFromUser(QUI\Interfaces\Users\User $User): array
    {
        $result = [];

        $hashes = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('hash'))
            ->from(Doctrine::quoteIdentifier($this->tableOrderProcess()))
            ->where(Doctrine::quoteIdentifier('customerId') . ' = :customerId')
            ->setParameter('customerId', $User->getUUID())
            ->executeQuery()
            ->fetchFirstColumn();

        foreach ($hashes as $hash) {
            try {
                $result[] = $this->getOrderInProcess($hash);
            } catch (\Exception) {
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
        return (int)QUI::getDataBaseConnection()->createQueryBuilder()
            ->select('COUNT(' . Doctrine::quoteIdentifier('id') . ')')
            ->from(Doctrine::quoteIdentifier($this->tableOrderProcess()))
            ->where(Doctrine::quoteIdentifier('customerId') . ' = :customerId')
            ->setParameter('customerId', $User->getUUID())
            ->executeQuery()
            ->fetchOne();
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
        $hash = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('hash'))
            ->from(Doctrine::quoteIdentifier($this->tableOrderProcess()))
            ->where(Doctrine::quoteIdentifier('customerId') . ' = :customerId')
            ->andWhere(Doctrine::quoteIdentifier('successful') . ' = :successful')
            ->setParameter('customerId', $User->getUUID())
            ->setParameter('successful', 0)
            ->orderBy(Doctrine::quoteIdentifier('c_date'), 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if ($hash === false) {
            try {
                $result = QUI::getEvents()->fireEvent('orderProcessGetOrder');

                foreach ($result as $Order) {
                    if ($Order instanceof OrderInProcess) {
                        return $Order;
                    }
                }
            } catch (\Exception) {
            }

            throw new QUI\ERP\Order\Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.no.orders.found'),
                self::ERROR_NO_ORDERS_FOUND
            );
        }

        return $this->getOrderInProcess($hash);
    }

    /**
     * Return the data of a wanted order
     *
     * @param integer|string $orderId
     * @return array<string, mixed>
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Database\Exception
     */
    public function getOrderProcessData(int | string $orderId): array
    {
        $QueryBuilder = QUI::getDataBaseConnection()->createQueryBuilder();
        $result = $QueryBuilder
            ->select('*')
            ->from(Doctrine::quoteIdentifier($this->tableOrderProcess()))
            ->where($QueryBuilder->expr()->or(
                Doctrine::quoteIdentifier('id') . ' = :orderId',
                Doctrine::quoteIdentifier('hash') . ' = :orderId'
            ))
            ->setParameter('orderId', $orderId)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if ($result === false) {
            throw new QUI\ERP\Order\Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
                self::ERROR_ORDER_NOT_FOUND
            );
        }

        return $result;
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
     * @param User|null $User - optional, user of the basket
     *
     * @return Basket
     *
     * @throws ExceptionBasketNotFound
     * @throws ExceptionStack
     * @throws QUI\Database\Exception
     * @throws QUI\Exception
     */
    public function getBasket(int | string $str, null | QUI\Interfaces\Users\User $User = null): Basket
    {
        if (is_numeric($str)) {
            return self::getBasketById($str, $User);
        }

        return self::getBasketByHash($str, $User);
    }

    /**
     * @param int|string $basketId
     * @param User|null $User - optional, user of the basket
     *
     * @return Basket
     *
     * @throws ExceptionBasketNotFound
     * @throws ExceptionStack
     * @throws QUI\Database\Exception
     * @throws QUI\Exception
     */
    public function getBasketById(int | string $basketId, null | QUI\Interfaces\Users\User $User = null): Basket
    {
        $data = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select(
                Doctrine::quoteIdentifier('id'),
                Doctrine::quoteIdentifier('uid')
            )
            ->from(Doctrine::quoteIdentifier($this->tableBasket()))
            ->where(Doctrine::quoteIdentifier('id') . ' = :basketId')
            ->setParameter('basketId', $basketId)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if ($data === false) {
            throw new ExceptionBasketNotFound([
                'quiqqer/order',
                'exception.basket.not.found'
            ]);
        }

        if ($User === null) {
            $User = QUI::getUserBySession();
        } else {
            $User = QUI::getUsers()->get($data['uid']);
        }

        $this->checkBasketPermissions($User);

        return new Basket($data['id'], $User);
    }

    /**
     * @param string $hash
     * @param User|null $User - optional, user of the basket
     *
     * @return Basket
     *
     * @throws ExceptionBasketNotFound
     * @throws ExceptionStack
     * @throws QUI\Database\Exception
     * @throws QUI\Exception
     */
    public function getBasketByHash(string $hash, null | QUI\Interfaces\Users\User $User = null): Basket
    {
        $data = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select(
                Doctrine::quoteIdentifier('id'),
                Doctrine::quoteIdentifier('uid')
            )
            ->from(Doctrine::quoteIdentifier($this->tableBasket()))
            ->where(Doctrine::quoteIdentifier('hash') . ' = :hash')
            ->setParameter('hash', $hash)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if ($data === false) {
            throw new ExceptionBasketNotFound([
                'quiqqer/order',
                'exception.basket.not.found'
            ]);
        }


        if ($User === null) {
            $User = QUI::getUserBySession();
        } else {
            $User = QUI::getUsers()->get($data['uid']);
        }

        $this->checkBasketPermissions($User);

        return new Basket($data['id'], $User);
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @return QUI\ERP\Order\Basket\Basket
     *
     * @throws Exception
     * @throws ExceptionBasketNotFound
     * @throws QUI\Database\Exception
     * @throws QUI\Exception
     */
    public function getBasketFromUser(QUI\Interfaces\Users\User $User): Basket
    {
        $this->checkBasketPermissions($User);

        $basketId = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier($this->tableBasket()))
            ->where(Doctrine::quoteIdentifier('uid') . ' = :uid')
            ->setParameter('uid', $User->getUUID())
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if ($basketId === false) {
            throw new ExceptionBasketNotFound([
                'quiqqer/order',
                'exception.basket.not.found'
            ]);
        }

        return new Basket($basketId, $User);
    }

    /**
     * Return the data from a basket
     *
     * @param integer|string $basketId
     * @param null|QUI\Interfaces\Users\User $User
     * @return array<string, mixed>
     *
     * @throws Exception
     * @throws ExceptionBasketNotFound
     * @throws QUI\Database\Exception
     * @throws QUI\Exception
     */
    public function getBasketData(int | string $basketId, null | QUI\Interfaces\Users\User $User = null): array
    {
        if ($User === null) {
            $User = QUI::getUserBySession();
        }

        $this->checkBasketPermissions($User);

        $data = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select('*')
            ->from(Doctrine::quoteIdentifier($this->tableBasket()))
            ->where(Doctrine::quoteIdentifier('id') . ' = :basketId')
            ->andWhere(Doctrine::quoteIdentifier('uid') . ' = :uid')
            ->setParameter('basketId', (int)$basketId)
            ->setParameter('uid', $User->getUUID())
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if ($data === false) {
            throw new ExceptionBasketNotFound([
                'quiqqer/order',
                'exception.basket.not.found'
            ]);
        }

        return $data;
    }

    /**
     * Basket permission check
     *
     * @param QUI\Interfaces\Users\User $User
     * @throws QUI\Exception
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

            if ($User->getUUID() === QUI::getUserBySession()->getUUID()) {
                return true;
            }

            return false;
        };

        if ($hasPermissions() === false) {
            throw new QUI\Exception([
                'quiqqer/order',
                'exception.basket.no.permissions'
            ]);
        }
    }

    //endregion
}
