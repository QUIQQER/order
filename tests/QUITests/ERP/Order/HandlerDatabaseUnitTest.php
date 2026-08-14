<?php

namespace QUITests\ERP\Order;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Countries\Manager as CountriesManager;
use QUI\ERP\Currency\Handler as CurrencyHandler;
use QUI\ERP\Order\Basket\ExceptionBasketNotFound;
use QUI\ERP\Order\Exception;
use QUI\ERP\Order\Handler;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\Order\OrderView;
use QUI\ERP\Order\Settings;
use QUI\Interfaces\Users\User;
use QUI\Permissions\Manager as PermissionManager;
use QUI\Permissions\Permission;
use QUI\Update;
use QUI\Utils\Singleton;
use QUITests\ERP\Order\Fixtures\TestableHandler;
use ReflectionProperty;

class HandlerDatabaseUnitTest extends TestCase
{
    private Connection $originalConnection;
    private Connection $connection;
    private TestableHandler $Handler;
    private ?PermissionManager $originalPermissionManager;
    private mixed $originalPermissionUser;

    /** @var array<string, mixed> */
    private array $originalCurrencyState;

    /** @var array<string, mixed> */
    private array $originalCountriesState;

    private mixed $originalSessionCountry;
    private mixed $originalSessionUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = QUI::getDataBaseConnection();
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true
        ]);
        $this->Handler = new TestableHandler();
        $this->originalPermissionManager = QUI::$Rights;
        $this->originalPermissionUser = (new ReflectionProperty(Permission::class, 'User'))->getValue();
        $this->originalCurrencyState = $this->getCurrencyState();
        $this->originalCountriesState = $this->getCountriesState();
        $this->originalSessionCountry = QUI::getSession()->get('country');
        $Users = QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $this->originalSessionUser = $Session->getValue($Users);
        $Session->setValue($Users, $Users->getSystemUser());

        $this->setConnection($this->connection);
        QUI::$Rights = null;
        Permission::setUser(QUI::getUsers()->getSystemUser());
        $this->setCurrencyState([
            'currencies' => [],
            'Default' => null,
            'RuntimeCurrency' => null
        ]);
        $this->setCountriesState([
            'countries' => [],
            'DefaultCountry' => null
        ]);
        QUI::getSession()->set('country', 'DE');

        PermissionManager::setup();
        Update::importPermissions(
            OPT_DIR . 'quiqqer/currency/permissions.xml',
            'quiqqer/currency'
        );
        Update::importDatabase(OPT_DIR . 'quiqqer/currency/database.xml');
        Update::importDatabase(OPT_DIR . 'quiqqer/areas/database.xml');
        Update::importDatabase(OPT_DIR . 'quiqqer/payment-transactions/database.xml');
        Update::importDatabase(dirname(__DIR__, 4) . '/database.xml');

        $this->connection->insert(CurrencyHandler::table(), [
            'currency' => 'EUR',
            'rate' => 1,
            'autoupdate' => 0,
            'precision' => 2,
            'type' => CurrencyHandler::CURRENCY_TYPE_DEFAULT,
            'customData' => null
        ]);
        $this->connection->insert(QUI::getDBTableName('areas'), [
            'id' => 1,
            'countries' => 'DE',
            'data' => '{}'
        ]);
        $this->createCountriesFixture();

        $this->insertFixtures();
    }

    protected function tearDown(): void
    {
        $this->setConnection($this->originalConnection);
        QUI::$Rights = $this->originalPermissionManager;
        (new ReflectionProperty(Permission::class, 'User'))->setValue(
            null,
            $this->originalPermissionUser
        );
        $this->setCurrencyState($this->originalCurrencyState);
        $this->setCountriesState($this->originalCountriesState);

        if ($this->originalSessionCountry === false) {
            QUI::getSession()->del('country');
        } else {
            QUI::getSession()->set('country', $this->originalSessionCountry);
        }

        (new ReflectionProperty(QUI::getUsers(), 'Session'))->setValue(
            QUI::getUsers(),
            $this->originalSessionUser
        );

        $this->connection->close();

        parent::tearDown();
    }

    public function testHashLookupPrioritizesOrderAndFallsBackToOrderProcess(): void
    {
        self::assertInstanceOf(Order::class, $this->Handler->getOrderByHash('order-a'));
        self::assertSame(['1'], $this->normalizeIds($this->Handler->getLoadedOrderIds()));

        $this->Handler->clearLoadedIds();

        self::assertInstanceOf(OrderInProcess::class, $this->Handler->getOrderByHash('process-new'));
        self::assertSame(['11'], $this->normalizeIds($this->Handler->getLoadedOrderProcessIds()));

        try {
            $this->Handler->getOrderByHash('missing');
            self::fail('A missing order hash must throw an exception.');
        } catch (Exception $Exception) {
            self::assertSame(Handler::ERROR_ORDER_NOT_FOUND, $Exception->getCode());
        }
    }

    public function testGlobalProcessAndIdLookupsUseAllowedDatabaseFields(): void
    {
        self::assertInstanceOf(Order::class, $this->Handler->getOrderByGlobalProcessId('global-a'));
        self::assertSame(['1'], $this->normalizeIds($this->Handler->getLoadedOrderIds()));

        $this->Handler->clearLoadedIds();
        self::assertCount(2, $this->Handler->getOrdersByGlobalProcessId('global-a'));
        self::assertSame(['1', '2'], $this->normalizeIds($this->Handler->getLoadedOrderIds()));
        self::assertSame([], $this->Handler->getOrdersByGlobalProcessId('missing'));

        $this->Handler->clearLoadedIds();
        self::assertInstanceOf(Order::class, $this->Handler->getOrderById('order-a'));
        self::assertInstanceOf(Order::class, $this->Handler->getOrderById(2));
        self::assertInstanceOf(OrderInProcess::class, $this->Handler->getOrderById(11));
        self::assertSame(['1', '2'], $this->normalizeIds($this->Handler->getLoadedOrderIds()));
        self::assertSame(['11'], $this->normalizeIds($this->Handler->getLoadedOrderProcessIds()));

        try {
            $this->Handler->getOrderById(999);
            self::fail('A missing order ID must throw an exception.');
        } catch (Exception $Exception) {
            self::assertSame(Handler::ERROR_ORDER_NOT_FOUND, $Exception->getCode());
        }
    }

    public function testOrderDataSupportsHashAndIdAndCountsCustomerOrders(): void
    {
        self::assertSame('order-a', $this->Handler->getOrderData('order-a')['hash']);
        self::assertSame('order-b', $this->Handler->getOrderData(2)['hash']);

        $User = $this->createUser('user-a');
        self::assertSame(2, $this->Handler->countOrdersByUser($User));

        try {
            $this->Handler->getOrderData('missing');
            self::fail('Missing order data must throw an exception.');
        } catch (Exception $Exception) {
            self::assertSame(Handler::ERROR_ORDER_NOT_FOUND, $Exception->getCode());
        }
    }

    public function testFinalOrderCreatesViewAfterCompleteSqliteHydration(): void
    {
        $Order = new Order('order-a');
        $View = $Order->getView();

        self::assertInstanceOf(OrderView::class, $View);
        self::assertSame(1, $View->getId());
        self::assertSame('order-a', $View->getUUID());
    }

    public function testFinalOrderPersistsPaymentStatusAndFrontendMessages(): void
    {
        $Order = new Order('order-a');

        $Order->setPaymentStatus(QUI\ERP\Constants::PAYMENT_STATUS_PART);
        $Order->addFrontendMessage('Visible final order message');

        $storedOrder = $this->connection->fetchAssociative(
            'SELECT paid_status, frontendMessages FROM ' . $this->Handler->table() . ' WHERE hash = ?',
            ['order-a']
        );
        self::assertIsArray($storedOrder);
        self::assertSame(QUI\ERP\Constants::PAYMENT_STATUS_PART, (int)$storedOrder['paid_status']);
        self::assertSame(
            QUI\ERP\Constants::PAYMENT_STATUS_PART,
            $Order->getAttribute('paid_status')
        );
        self::assertStringContainsString('Visible final order message', $storedOrder['frontendMessages']);

        $Order->clearFrontendMessages();

        self::assertTrue($Order->getFrontendMessages()->isEmpty());
        self::assertSame(
            $Order->getFrontendMessages()->toJSON(),
            $this->connection->fetchOne(
                'SELECT frontendMessages FROM ' . $this->Handler->table() . ' WHERE hash = ?',
                ['order-a']
            )
        );
    }

    public function testOrderProcessQueriesListCountAndSelectLatestOpenEntry(): void
    {
        $User = $this->createUser('user-a');

        self::assertInstanceOf(
            OrderInProcess::class,
            $this->Handler->getOrderInProcessByHash('process-new')
        );
        self::assertSame(['11'], $this->normalizeIds($this->Handler->getLoadedOrderProcessIds()));

        $this->Handler->clearLoadedIds();
        self::assertCount(3, $this->Handler->getOrdersInProcessFromUser($User));
        self::assertSame(
            ['process-old', 'process-new', 'process-successful'],
            $this->normalizeIds($this->Handler->getLoadedOrderProcessIds())
        );
        self::assertSame(3, $this->Handler->countOrdersInProcessFromUser($User));

        $this->Handler->clearLoadedIds();
        self::assertInstanceOf(OrderInProcess::class, $this->Handler->getLastOrderInProcessFromUser($User));
        self::assertSame(['process-new'], $this->normalizeIds($this->Handler->getLoadedOrderProcessIds()));

        self::assertSame('process-new', $this->Handler->getOrderProcessData(11)['hash']);
        self::assertSame('process-old', $this->Handler->getOrderProcessData('process-old')['hash']);

        try {
            $this->Handler->getOrderProcessData('missing');
            self::fail('Missing order process data must throw an exception.');
        } catch (Exception $Exception) {
            self::assertSame(Handler::ERROR_ORDER_NOT_FOUND, $Exception->getCode());
        }
    }

    public function testOrderInProcessCreatesViewAfterCompleteSqliteHydration(): void
    {
        $OrderInProcess = new OrderInProcess('process-new');
        $View = $OrderInProcess->getView();

        self::assertInstanceOf(OrderView::class, $View);
        self::assertSame(11, $View->getId());
        self::assertSame('process-new', $View->getUUID());
    }

    public function testOrderInProcessPaymentStatusUpdatesDatabaseAndLoadedObject(): void
    {
        $OrderInProcess = new OrderInProcess('process-new');

        self::assertSame(0, $OrderInProcess->getAttribute('paid_status'));

        $OrderInProcess->setPaymentStatus(QUI\ERP\Constants::PAYMENT_STATUS_PART);

        self::assertSame(
            QUI\ERP\Constants::PAYMENT_STATUS_PART,
            $OrderInProcess->getAttribute('paid_status')
        );
        self::assertSame(
            QUI\ERP\Constants::PAYMENT_STATUS_PART,
            (int)$this->connection->fetchOne(
                'SELECT paid_status FROM ' . $this->Handler->tableOrderProcess() . ' WHERE hash = ?',
                ['process-new']
            )
        );
    }

    public function testOrderInProcessPersistsFrontendMessages(): void
    {
        $OrderInProcess = new OrderInProcess('process-new');

        $OrderInProcess->addFrontendMessage('Visible process message');

        $storedMessages = (string)$this->connection->fetchOne(
            'SELECT frontendMessages FROM ' . $this->Handler->tableOrderProcess() . ' WHERE hash = ?',
            ['process-new']
        );
        self::assertStringContainsString('Visible process message', $storedMessages);
        self::assertSame(
            'Visible process message',
            $OrderInProcess->getFrontendMessages()->toArray()[0]['message']
        );

        $OrderInProcess->clearFrontendMessages();

        self::assertTrue($OrderInProcess->getFrontendMessages()->isEmpty());
        self::assertSame(
            $OrderInProcess->getFrontendMessages()->toJSON(),
            $this->connection->fetchOne(
                'SELECT frontendMessages FROM ' . $this->Handler->tableOrderProcess() . ' WHERE hash = ?',
                ['process-new']
            )
        );
    }

    public function testOrderInProcessRebuildsArticlesFromBasketPriceFactors(): void
    {
        $OrderInProcess = new OrderInProcess('process-price');

        self::assertSame(0, $OrderInProcess->count());

        $OrderInProcess->addPriceFactors([]);

        self::assertSame(0, $OrderInProcess->count());
        self::assertJson((string)$this->connection->fetchOne(
            'SELECT articles FROM ' . $this->Handler->tableOrderProcess() . ' WHERE hash = ?',
            ['process-price']
        ));
    }

    public function testOrderInProcessCalculatesPlannedPaymentWithoutCreatingOrder(): void
    {
        $OrderInProcess = new OrderInProcess('process-price');
        $OrderInProcess->setPaymentStatus(QUI\ERP\Constants::PAYMENT_STATUS_PLAN);

        $OrderInProcess->calculatePayments();

        $storedPayment = $this->connection->fetchAssociative(
            'SELECT paid_status, paid_data, paid_date, order_id FROM '
            . $this->Handler->tableOrderProcess()
            . ' WHERE hash = ?',
            ['process-price']
        );

        self::assertIsArray($storedPayment);
        self::assertSame(QUI\ERP\Constants::PAYMENT_STATUS_PLAN, (int)$storedPayment['paid_status']);
        self::assertSame('[]', $storedPayment['paid_data']);
        self::assertSame(0, (int)$storedPayment['paid_date']);
        self::assertNull($storedPayment['order_id']);
        self::assertSame(
            QUI\ERP\Constants::PAYMENT_STATUS_PLAN,
            $OrderInProcess->getAttribute('paid_status')
        );
    }

    public function testOrderInProcessConvertsToFinalOrderWithoutInvoiceModule(): void
    {
        $SingletonInstances = new ReflectionProperty(Singleton::class, 'instances');
        $originalInstances = $SingletonInstances->getValue();
        $Settings = new Settings();
        (new ReflectionProperty($Settings, 'isInvoiceInstalled'))->setValue($Settings, false);
        $instances = $originalInstances;
        $instances[Settings::class] = $Settings;
        $SingletonInstances->setValue(null, $instances);
        $Config = Settings::getConfig();
        $originalOrderIndex = $Config->getValue('order', 'orderCurrentIdIndex');

        try {
            self::assertFalse($Settings->createInvoiceOnOrder());
            self::assertFalse($Settings->createInvoiceByPayment());

            $OrderInProcess = new OrderInProcess('process-price');
            $Order = $OrderInProcess->createOrder(QUI::getUsers()->getSystemUser());

            self::assertInstanceOf(Order::class, $Order);
            self::assertSame('process-price', $Order->getUUID());
            self::assertFalse($Order->hasInvoice());
            self::assertFalse(
                $this->connection->fetchOne(
                    'SELECT 1 FROM ' . $this->Handler->tableOrderProcess() . ' WHERE hash = ?',
                    ['process-price']
                )
            );
            self::assertSame(
                'process-price',
                $this->connection->fetchOne(
                    'SELECT hash FROM ' . $this->Handler->table() . ' WHERE hash = ?',
                    ['process-price']
                )
            );
        } finally {
            $Config->set('order', 'orderCurrentIdIndex', $originalOrderIndex);
            $Config->save();
            $SingletonInstances->setValue(null, $originalInstances);
        }
    }

    public function testMissingLatestOrderProcessUsesDocumentedErrorCode(): void
    {
        try {
            $this->Handler->getLastOrderInProcessFromUser($this->createUser('missing-user'));
            self::fail('A user without open orders must trigger the documented exception.');
        } catch (Exception $Exception) {
            self::assertSame(Handler::ERROR_NO_ORDERS_FOUND, $Exception->getCode());
        }
    }

    public function testBasketDataUsesIdAndSessionUserWithoutSqlConcatenation(): void
    {
        $SessionUser = QUI::getUserBySession();
        $this->connection->insert($this->Handler->tableBasket(), [
            'id' => 20,
            'uid' => $SessionUser->getUUID(),
            'hash' => 'basket-a',
            'products' => '[]'
        ]);

        $data = $this->Handler->getBasketData(20, $SessionUser);

        self::assertSame('basket-a', $data['hash']);
        self::assertSame((string)$SessionUser->getUUID(), $data['uid']);

        try {
            $this->Handler->getBasketData('20 OR 1=1', $SessionUser);
            self::fail('A manipulated basket ID must not match a row.');
        } catch (ExceptionBasketNotFound) {
            self::assertTrue(true);
        }
    }

    private function insertFixtures(): void
    {
        $orders = [
            [1, 'order-a', 'global-a', 'user-a', '2026-01-01 10:00:00'],
            [2, 'order-b', 'global-a', 'user-a', '2026-01-02 10:00:00'],
            [3, 'order-c', 'global-c', 'user-b', '2026-01-03 10:00:00']
        ];

        foreach ($orders as [$id, $hash, $globalId, $customerId, $date]) {
            $this->connection->insert($this->Handler->table(), [
                'id' => $id,
                'hash' => $hash,
                'global_process_id' => $globalId,
                'customerId' => $customerId,
                'status' => 1,
                'paid_status' => 0,
                'successful' => 0,
                'c_date' => $date,
                'paid_date' => null,
                'c_user' => $customerId
            ]);
        }

        $processes = [
            [10, 'process-old', 'user-a', 0, '2026-01-01 10:00:00'],
            [11, 'process-new', 'user-a', 0, '2026-01-03 10:00:00'],
            [12, 'process-successful', 'user-a', 1, '2026-01-04 10:00:00'],
            [13, 'process-other', 'user-b', 0, '2026-01-05 10:00:00'],
            [
                14,
                'process-price',
                (string)QUI::getUsers()->getSystemUser()->getUUID(),
                0,
                '2026-01-06 10:00:00'
            ]
        ];

        foreach ($processes as [$id, $hash, $customerId, $successful, $date]) {
            $this->connection->insert($this->Handler->tableOrderProcess(), [
                'id' => $id,
                'status' => 1,
                'hash' => $hash,
                'customerId' => $customerId,
                'paid_status' => 0,
                'successful' => $successful,
                'c_date' => $date,
                'c_user' => $customerId
            ]);
        }

        $this->connection->insert($this->Handler->tableBasket(), [
            'id' => 30,
            'uid' => (string)QUI::getUsers()->getSystemUser()->getUUID(),
            'products' => '[]',
            'hash' => 'process-price'
        ]);
    }

    private function createCountriesFixture(): void
    {
        $Table = new Table(CountriesManager::getDataBaseTableName());
        $Table->addColumn('countries_id', 'integer', ['autoincrement' => true]);
        $Table->addColumn('countries_name', 'string', ['length' => 64]);
        $Table->addColumn('countries_iso_code_2', 'string', ['length' => 2]);
        $Table->addColumn('countries_iso_code_3', 'string', ['length' => 3]);
        $Table->addColumn('numeric_code', 'string', ['length' => 4]);
        $Table->addColumn('language', 'string', ['length' => 3]);
        $Table->addColumn('languages', 'text');
        $Table->addColumn('currency', 'string', ['length' => 3]);
        $Table->addColumn('active', 'smallint', ['default' => 1]);
        $Table->setPrimaryKey(['countries_id']);
        $this->connection->createSchemaManager()->createTable($Table);

        $this->connection->insert(CountriesManager::getDataBaseTableName(), [
            'countries_name' => 'Germany',
            'countries_iso_code_2' => 'DE',
            'countries_iso_code_3' => 'DEU',
            'numeric_code' => '276',
            'language' => 'de',
            'languages' => '["de"]',
            'currency' => 'EUR',
            'active' => 1
        ]);
    }

    private function createUser(string $uuid): User
    {
        $User = $this->createMock(User::class);
        $User->method('getUUID')->willReturn($uuid);

        return $User;
    }

    /**
     * @param list<int|string> $ids
     * @return list<string>
     */
    private function normalizeIds(array $ids): array
    {
        return array_map('strval', $ids);
    }

    private function setConnection(Connection $Connection): void
    {
        $QueryBuilder = new ReflectionProperty(QUI::class, 'QueryBuilder');
        $QueryBuilder->setValue(null, $Connection);
    }

    /**
     * @return array<string, mixed>
     */
    private function getCurrencyState(): array
    {
        $state = [];

        foreach (['currencies', 'Default', 'RuntimeCurrency'] as $property) {
            $state[$property] = (new ReflectionProperty(CurrencyHandler::class, $property))->getValue();
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function setCurrencyState(array $state): void
    {
        foreach ($state as $property => $value) {
            (new ReflectionProperty(CurrencyHandler::class, $property))->setValue(null, $value);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getCountriesState(): array
    {
        $state = [];

        foreach (['countries', 'DefaultCountry'] as $property) {
            $state[$property] = (new ReflectionProperty(CountriesManager::class, $property))->getValue();
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function setCountriesState(array $state): void
    {
        foreach ($state as $property => $value) {
            (new ReflectionProperty(CountriesManager::class, $property))->setValue(null, $value);
        }
    }
}
