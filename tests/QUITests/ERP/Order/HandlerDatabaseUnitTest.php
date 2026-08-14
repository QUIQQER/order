<?php

namespace QUITests\ERP\Order;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Countries\Manager as CountriesManager;
use QUI\ERP\Currency\Handler as CurrencyHandler;
use QUI\ERP\Accounting\Invoice\Factory as InvoiceFactory;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Customer\Utils as CustomerUtils;
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
use QUITests\ERP\Order\Fixtures\TestableOrderProcess;
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

        if (QUI::getPackageManager()->isInstalled('quiqqer/salesorders')) {
            Update::importDatabase(OPT_DIR . 'quiqqer/salesorders/database.xml');
        }

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

    public function testFinalOrderPersistsProcessingStatusHistoryAndCustomData(): void
    {
        $Order = new Order('order-a');
        $Status = $this->createMock(QUI\ERP\Order\ProcessingStatus\Status::class);
        $Status->method('getId')->willReturn(888888);

        $Order->setProcessingStatus($Status);
        $Order->update(QUI::getUsers()->getSystemUser());
        $Order->addCustomDataEntry('sqlite-test', ['persisted' => true]);

        $storedOrder = $this->connection->fetchAssociative(
            'SELECT status, history, custom_data FROM ' . $this->Handler->table() . ' WHERE hash = ?',
            ['order-a']
        );
        self::assertIsArray($storedOrder);
        self::assertSame(888888, (int)$storedOrder['status']);
        self::assertStringContainsString('888888', $storedOrder['history']);
        self::assertSame(
            ['persisted' => true],
            json_decode($storedOrder['custom_data'], true)['sqlite-test']
        );
        self::assertSame(['persisted' => true], $Order->getCustomDataEntry('sqlite-test'));
    }

    public function testFinalOrderCreatesAndPostsInvoiceWhenOptionalModuleIsAvailable(): void
    {
        if (!Settings::getInstance()->isInvoiceInstalled()) {
            self::markTestSkipped('The optional invoice module is not installed.');
        }

        $SingletonInstances = new ReflectionProperty(Singleton::class, 'instances');
        $originalInstances = $SingletonInstances->getValue();
        $temporaryInvoiceTable = 'phpunit_temporary_invoice';
        $Table = new Table($temporaryInvoiceTable);
        $Table->addColumn('hash', 'string', ['length' => 64]);
        $Table->addColumn('shipping_id', 'string', ['notnull' => false]);
        $Table->addColumn('paid_status', 'integer', ['notnull' => false]);
        $Table->addColumn('payment_data', 'text', ['notnull' => false]);
        $Table->addColumn('currency_data', 'text', ['notnull' => false]);
        $Table->addColumn('currency', 'string', ['length' => 8, 'notnull' => false]);
        $Table->addColumn('customer_id', 'string', ['length' => 64, 'notnull' => false]);
        $Table->addColumn('customer_data', 'text', ['notnull' => false]);
        $Table->setPrimaryKey(['hash']);
        $this->connection->createSchemaManager()->createTable($Table);
        $this->connection->insert($temporaryInvoiceTable, ['hash' => 'temporary-invoice-uuid']);

        $Payment = $this->createMock(Payment::class);
        $Payment->method('getId')->willReturn(7);
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getUUID')->willReturn((string)QUI::getUsers()->getSystemUser()->getUUID());
        $Customer->method('getAttributes')->willReturn(['uuid' => $Customer->getUUID()]);
        $InvoiceAddress = $this->createMock(QUI\ERP\Address::class);
        $InvoiceAddress->method('getUUID')->willReturn('invoice-address-uuid');
        $InvoiceAddress->method('getName')->willReturn('Invoice Recipient');
        $InvoiceAddress->method('getAttribute')->willReturn('value');
        $InvoiceAddress->method('toJSON')->willReturn('{"id":"invoice-address-uuid"}');
        $DeliveryAddress = $this->createMock(QUI\ERP\Address::class);
        $DeliveryAddress->method('getUUID')->willReturn('delivery-address-uuid');
        $DeliveryAddress->method('toJSON')->willReturn('{"id":"delivery-address-uuid"}');
        $InvoiceArticles = $this->createMock(QUI\ERP\Accounting\ArticleList::class);
        $TemporaryInvoice = $this->createMock(InvoiceTemporary::class);
        $TemporaryInvoice->method('getUUID')->willReturn('temporary-invoice-uuid');
        $TemporaryInvoice->method('getArticles')->willReturn($InvoiceArticles);
        $TemporaryInvoice->expects(self::once())->method('setCustomer')->with($Customer);
        $TemporaryInvoice->expects(self::once())->method('setDeliveryAddress')->with($DeliveryAddress);
        $TemporaryInvoice->expects(self::once())->method('save')->with(QUI::getUsers()->getSystemUser());
        $TemporaryInvoice->expects(self::once())->method('validate');
        $Invoice = $this->createMock(Invoice::class);
        $Invoice->method('getUUID')->willReturn('posted-invoice-uuid');
        $TemporaryInvoice->expects(self::once())
            ->method('post')
            ->with(QUI::getUsers()->getSystemUser())
            ->willReturn($Invoice);
        $Factory = $this->getMockBuilder(InvoiceFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createInvoice'])
            ->getMock();
        $Factory->expects(self::once())
            ->method('createInvoice')
            ->willReturn($TemporaryInvoice);
        $InvoiceHandler = $this->getMockBuilder(InvoiceHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['temporaryInvoiceTable', 'getTemporaryInvoice', 'getInvoice'])
            ->getMock();
        $InvoiceHandler->method('temporaryInvoiceTable')->willReturn($temporaryInvoiceTable);
        $InvoiceHandler->method('getTemporaryInvoice')->willReturn($TemporaryInvoice);
        $InvoiceHandler->method('getInvoice')->with('posted-invoice-uuid')->willReturn($Invoice);
        $InvoiceSettings = $this->getMockBuilder(Settings::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['forceCreateInvoice', 'isInvoiceInstalled', 'get'])
            ->getMock();
        $InvoiceSettings->method('forceCreateInvoice')->willReturn(false);
        $InvoiceSettings->method('isInvoiceInstalled')->willReturn(true);
        $InvoiceSettings->method('get')->willReturn(true);
        $CustomerUtils = $this->getMockBuilder(CustomerUtils::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPaymentTimeForUser'])
            ->getMock();
        $CustomerUtils->method('getPaymentTimeForUser')->willReturn(14);
        $instances = $originalInstances;
        $instances[InvoiceFactory::class] = $Factory;
        $instances[InvoiceHandler::class] = $InvoiceHandler;
        $instances[Settings::class] = $InvoiceSettings;
        $instances[CustomerUtils::class] = $CustomerUtils;
        $SingletonInstances->setValue(null, $instances);
        $Order = $this->getMockBuilder(Order::class)
            ->setConstructorArgs(['order-a'])
            ->onlyMethods([
                'isPosted',
                'refresh',
                'getPayment',
                'getCustomer',
                'getInvoiceAddress',
                'getDeliveryAddress',
                'getReferenceData',
                'getTransactions'
            ])
            ->getMock();
        $Order->method('isPosted')->willReturn(false);
        $Order->expects(self::once())->method('refresh');
        $Order->method('getPayment')->willReturn($Payment);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getInvoiceAddress')->willReturn($InvoiceAddress);
        $Order->method('getDeliveryAddress')->willReturn($DeliveryAddress);
        $Order->method('getReferenceData')->willReturn([]);
        $Order->method('getTransactions')->willReturn([]);

        try {
            self::assertSame(
                $Invoice,
                $Order->createInvoice()
            );
            self::assertSame(
                'temporary-invoice-uuid',
                $Order->getAttribute('temporary_invoice_id')
            );
            self::assertSame(
                'temporary-invoice-uuid',
                $this->connection->fetchOne(
                    'SELECT temporary_invoice_id FROM ' . $this->Handler->table() . ' WHERE hash = ?',
                    ['order-a']
                )
            );
            self::assertSame(
                'posted-invoice-uuid',
                $this->connection->fetchOne(
                    'SELECT invoice_id FROM ' . $this->Handler->table() . ' WHERE hash = ?',
                    ['order-a']
                )
            );
            self::assertSame(
                (string)$Customer->getUUID(),
                $this->connection->fetchOne(
                    'SELECT customer_id FROM ' . $temporaryInvoiceTable . ' WHERE hash = ?',
                    ['temporary-invoice-uuid']
                )
            );
        } finally {
            $SingletonInstances->setValue(null, $originalInstances);
        }
    }

    public function testFinalOrderCreatesSalesOrderWhenOptionalModuleIsAvailable(): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/salesorders')) {
            self::markTestSkipped('The optional salesorders module is not installed.');
        }

        $SingletonInstances = new ReflectionProperty(Singleton::class, 'instances');
        $originalInstances = $SingletonInstances->getValue();
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getUUID')->willReturn((string)QUI::getUsers()->getSystemUser()->getUUID());
        $InvoiceAddress = $this->createMock(QUI\ERP\Address::class);
        $InvoiceAddress->method('getUUID')->willReturn('sales-address-uuid');
        $InvoiceAddress->method('toJSON')->willReturn('{"id":"sales-address-uuid"}');
        $CustomerUtils = $this->getMockBuilder(CustomerUtils::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContactPersonAddress'])
            ->getMock();
        $CustomerUtils->method('getContactPersonAddress')->willReturn(false);
        $instances = $originalInstances;
        $instances[CustomerUtils::class] = $CustomerUtils;
        $SingletonInstances->setValue(null, $instances);
        $Order = $this->getMockBuilder(Order::class)
            ->setConstructorArgs(['order-a'])
            ->onlyMethods([
                'getPayment',
                'getCustomer',
                'getInvoiceAddress',
                'getDeliveryAddress',
                'getReferenceData'
            ])
            ->getMock();
        $Order->method('getPayment')->willReturn(null);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getInvoiceAddress')->willReturn($InvoiceAddress);
        $Order->method('getDeliveryAddress')->willReturn($InvoiceAddress);
        $Order->method('getReferenceData')->willReturn([]);

        try {
            $SalesOrder = $Order->createSalesOrder();

            self::assertInstanceOf(\QUI\ERP\SalesOrders\SalesOrder::class, $SalesOrder);
        } finally {
            $SingletonInstances->setValue(null, $originalInstances);
        }
    }

    public function testHandlerSendsPaymentSuccessMailWithResolvedCustomerData(): void
    {
        $SingletonInstances = new ReflectionProperty(Singleton::class, 'instances');
        $originalInstances = $SingletonInstances->getValue();
        $originalMailManager = QUI::$MailManager;
        $originalRewrite = QUI::$Rewrite;
        $CustomerAddress = $this->createMock(QUI\Users\Address::class);
        $CustomerAddress->method('getAttribute')
            ->with('contactPerson')
            ->willReturn('Customer Contact');
        $CustomerAddress->method('getName')->willReturn('Customer Name');
        $InvoiceAddress = $this->createMock(QUI\ERP\Address::class);
        $InvoiceAddress->method('getAttribute')->willReturnCallback(
            static fn(string $key): string => match ($key) {
                'contactPerson' => 'Invoice Contact',
                'company' => 'Customer Company',
                'salutation' => 'mr',
                'firstname' => 'Invoice',
                'lastname' => 'Recipient',
                default => ''
            }
        );
        $InvoiceAddress->method('getMailList')->willReturn(['fallback@example.test']);
        $InvoiceAddress->method('render')->willReturn('Rendered customer address');
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getLocale')->willReturn(QUI::getLocale());
        $Customer->method('getAddress')->willReturn($CustomerAddress);
        $Customer->method('getName')->willReturn('Customer Name');
        $Customer->method('getLang')->willReturn('de');
        $Customer->method('getUUID')->willReturn('customer-mail-uuid');
        $Customer->method('getAttribute')->with('email')->willReturn('customer@example.test');
        $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getInvoiceAddress')->willReturn($InvoiceAddress);
        $Order->method('getPrefixedNumber')->willReturn('ORD-42');
        $Order->method('getUUID')->willReturn('mail-order-uuid');
        $Order->method('getCreateDate')->willReturn('2026-08-14 07:00:00');
        $Order->method('getAttribute')->with('project_name')->willReturn(false);
        $ContactAddress = $this->createMock(QUI\ERP\Address::class);
        $ContactAddress->method('getName')->willReturn('Primary Contact');
        $CustomerUtils = $this->getMockBuilder(CustomerUtils::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContactPersonAddress', 'getEmailByCustomer'])
            ->getMock();
        $CustomerUtils->method('getContactPersonAddress')->with($Customer)->willReturn($ContactAddress);
        $CustomerUtils->method('getEmailByCustomer')->with($Customer)->willReturn('customer@example.test');
        $instances = $originalInstances;
        $instances[CustomerUtils::class] = $CustomerUtils;
        $SingletonInstances->setValue(null, $instances);
        $Mailer = $this->createMock(QUI\Mail\Mailer::class);
        $Mailer->expects(self::exactly(3))->method('setSubject');
        $Mailer->expects(self::exactly(3))->method('setBody');
        $Mailer->expects(self::exactly(2))->method('addRecipient');
        $Mailer->expects(self::exactly(2))->method('send')->willReturn(true);
        $MailManager = $this->createMock(QUI\Mail\Manager::class);
        $MailManager->expects(self::exactly(3))->method('getMailer')->willReturn($Mailer);
        QUI::$MailManager = $MailManager;
        $Rewrite = $this->createMock(QUI\Rewrite::class);
        $Rewrite->method('getProject')->willReturn(null);
        QUI::$Rewrite = $Rewrite;
        $Config = Settings::getConfig();
        $originalAdminMails = $Config->getValue('order', 'orderAdminMails');
        $Config->set('order', 'orderAdminMails', 'admin@example.test');
        $Config->save();

        try {
            $this->Handler->sendOrderPaymentSuccessMail($Order);

            $MissingEmailUtils = $this->getMockBuilder(CustomerUtils::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getContactPersonAddress', 'getEmailByCustomer'])
                ->getMock();
            $MissingEmailUtils->method('getContactPersonAddress')->with($Customer)->willReturn($ContactAddress);
            $MissingEmailUtils->method('getEmailByCustomer')->with($Customer)->willReturn(false);
            $instances[CustomerUtils::class] = $MissingEmailUtils;
            $SingletonInstances->setValue(null, $instances);

            $this->Handler->sendOrderPaymentSuccessMail($Order);

            self::assertTrue(true);
        } finally {
            $Config->set('order', 'orderAdminMails', $originalAdminMails);
            $Config->save();
            QUI::$MailManager = $originalMailManager;
            QUI::$Rewrite = $originalRewrite;
            $SingletonInstances->setValue(null, $originalInstances);
        }
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

    public function testOrderInProcessDropsMissingFinalOrderReference(): void
    {
        $OrderInProcess = new OrderInProcess('process-orphaned');

        self::assertNull($OrderInProcess->getOrderId());
        self::assertSame('process-orphaned', $OrderInProcess->getPrefixedId());
    }

    public function testLinkedOrderInProcessRefreshesFinalOrderAndHandlesItsRemoval(): void
    {
        $OrderInProcess = new OrderInProcess('process-linked');

        self::assertSame('order-a', $OrderInProcess->getOrderId());
        $OrderInProcess->refresh();

        $this->connection->delete($this->Handler->table(), ['hash' => 'order-a']);

        self::assertSame('process-linked', $OrderInProcess->getPrefixedId());
        self::assertFalse($OrderInProcess->isPosted());
        self::assertFalse($OrderInProcess->hasInvoice());

        $OrderInProcess->refresh();
        self::assertSame('process-linked', $OrderInProcess->getUUID());
    }

    public function testSuccessfulLinkedOrderInProcessSkipsRefresh(): void
    {
        $OrderInProcess = new OrderInProcess('process-linked-successful');

        $this->connection->delete($this->Handler->tableOrderProcess(), [
            'hash' => 'process-linked-successful'
        ]);

        $OrderInProcess->refresh();

        self::assertSame(1, $OrderInProcess->isSuccessful());
    }

    public function testOrderInProcessPersistsProcessingStatusChange(): void
    {
        $OrderInProcess = new OrderInProcess('process-new');
        $Status = $this->createMock(QUI\ERP\Order\ProcessingStatus\Status::class);
        $Status->method('getId')->willReturn(999999);

        $OrderInProcess->setProcessingStatus($Status);
        $OrderInProcess->update(QUI::getUsers()->getSystemUser());

        self::assertSame(
            999999,
            (int)$this->connection->fetchOne(
                'SELECT status FROM ' . $this->Handler->tableOrderProcess() . ' WHERE hash = ?',
                ['process-new']
            )
        );
        self::assertStringContainsString(
            '999999',
            (string)$this->connection->fetchOne(
                'SELECT history FROM ' . $this->Handler->tableOrderProcess() . ' WHERE hash = ?',
                ['process-new']
            )
        );
    }

    public function testOrderProcessResolvesPersistedOrderAndBasketThroughBaseFlow(): void
    {
        $OrderInProcess = new OrderInProcess('process-price');
        $Process = new TestableOrderProcess();
        $Process->setTestOrder($OrderInProcess);
        $Process->setAttribute('step', 'CustomerData');

        self::assertInstanceOf(
            QUI\ERP\Order\Basket\BasketOrder::class,
            $Process->invokeBaseGetBasket()
        );

        $ProcessWithoutOrder = new TestableOrderProcess();
        self::assertInstanceOf(OrderInProcess::class, $ProcessWithoutOrder->invokeBaseGetOrder());
        self::assertInstanceOf(
            QUI\ERP\Order\Basket\Basket::class,
            $ProcessWithoutOrder->invokeBaseGetBasket()
        );

        $ProcessByBasketId = new TestableOrderProcess();
        $ProcessByBasketId->setAttribute('basketId', 30);
        self::assertInstanceOf(
            QUI\ERP\Order\Basket\Basket::class,
            $ProcessByBasketId->invokeBaseGetBasket()
        );
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
            ],
            [15, 'process-orphaned', 'flow-user', 0, '2026-01-07 10:00:00'],
            [16, 'process-linked', 'flow-user', 0, '2026-01-08 10:00:00'],
            [17, 'process-linked-successful', 'flow-user', 1, '2026-01-09 10:00:00']
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

        $this->connection->update(
            $this->Handler->tableOrderProcess(),
            ['order_id' => 'missing-order'],
            ['hash' => 'process-orphaned']
        );
        $this->connection->update(
            $this->Handler->tableOrderProcess(),
            ['order_id' => 'order-a'],
            ['hash' => 'process-linked']
        );
        $this->connection->update(
            $this->Handler->tableOrderProcess(),
            ['order_id' => 'order-a'],
            ['hash' => 'process-linked-successful']
        );

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
