<?php

namespace QUITests\ERP\Order;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\MySQLSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use DusanKasan\Knapsack\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI\Control;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Controls\Buttons\ProductToBasket;
use QUI\ERP\Order\EventHandling;
use QUI\ERP\Order\ProcessingStatus\Status;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Comments;
use QUI\ERP\Order\Order;
use QUI\ERP\Products\Product\Product;
use QUI\ERP\Products\Product\Types\AbstractType;
use QUI\Interfaces\Projects\Site;
use QUI\Projects\Project;
use QUI\Rewrite;
use QUI\Smarty\Collector;
use QUI\Template;
use ReflectionMethod;
use ReflectionProperty;
use QUI\Utils\Singleton;
use QUITests\ERP\Order\Fixtures\TestableHandler;

class EventHandlingUnitTest extends TestCase
{
    public function testProductViewButtonIsAppendedAndDisabledForUnavailableProduct(): void
    {
        $Product = $this->createMock(Product::class);
        $ProductControl = new Control([
            'data-qui-option-available' => false
        ]);
        $Collection = Collection::from([]);

        EventHandling::onQuiqqerProductsProductViewButtons(
            $Product,
            $Collection,
            $ProductControl
        );

        $buttons = iterator_to_array($Collection);
        self::assertCount(1, $buttons);
        self::assertInstanceOf(ProductToBasket::class, $buttons[0]);
        self::assertTrue($buttons[0]->getAttribute('disabled'));
    }

    public function testSimpleCheckoutRequestSelectsOrderProcessSite(): void
    {
        $CheckoutSite = $this->createMock(Site::class);
        $CheckoutSite->method('getUrlRewritten')->willReturn('/checkout/');
        $Project = $this->createMock(Project::class);
        $Project->method('getSites')->willReturn([$CheckoutSite]);
        $Rewrite = $this->createMock(Rewrite::class);
        $Rewrite->method('getProject')->willReturn($Project);
        $Rewrite->expects(self::once())->method('setSite')->with($CheckoutSite);

        (new ReflectionMethod(EventHandling::class, 'handleRequest'))
            ->invoke(null, $Rewrite, 'checkout');
    }

    public function testGuestOrderRouteLoadsOrderAndSelectsCheckoutSite(): void
    {
        $Users = \QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $originalUser = $Session->getValue($Users);
        $CheckoutSite = $this->createMock(Site::class);
        $CheckoutSite->method('getUrlRewritten')->willReturn('https://example.test/checkout');
        $CheckoutSite->expects(self::once())
            ->method('setAttribute')
            ->with('order::hash', 'route-order');
        $Project = $this->createMock(Project::class);
        $Project->method('getSites')->willReturn([$CheckoutSite]);
        $Rewrite = $this->createMock(Rewrite::class);
        $Rewrite->method('getProject')->willReturn($Project);
        $Rewrite->expects(self::once())->method('setSite')->with($CheckoutSite);
        $Order = $this->createMock(Order::class);
        $Order->method('getUUID')->willReturn('route-order');
        $Handler = new TestableHandler();
        $Handler->setResolvedOrder($Order);

        try {
            $Session->setValue($Users, $Users->getNobody());
            $this->withHandler($Handler, static function () use ($Rewrite): void {
                (new ReflectionMethod(EventHandling::class, 'handleRequest'))
                    ->invoke(null, $Rewrite, 'checkout/Order/route-order');
            });
        } finally {
            $Session->setValue($Users, $originalUser);
        }
    }

    public function testGuestSuccessfulProcessRouteConvertsAndDeletesTemporaryOrder(): void
    {
        $Users = \QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $originalUser = $Session->getValue($Users);
        $CheckoutSite = $this->createMock(Site::class);
        $CheckoutSite->method('getUrlRewritten')->willReturn('/checkout/');
        $CheckoutSite->expects(self::once())
            ->method('setAttribute')
            ->with('order::hash', 'converted-route-order');
        $Project = $this->createMock(Project::class);
        $Project->method('getSites')->willReturn([$CheckoutSite]);
        $Rewrite = $this->createMock(Rewrite::class);
        $Rewrite->method('getProject')->willReturn($Project);
        $Rewrite->expects(self::once())->method('setSite')->with($CheckoutSite);
        $FinalOrder = $this->createMock(Order::class);
        $FinalOrder->method('getUUID')->willReturn('converted-route-order');
        $ProcessOrder = $this->createMock(\QUI\ERP\Order\OrderInProcess::class);
        $ProcessOrder->method('isSuccessful')->willReturn(1);
        $ProcessOrder->expects(self::once())->method('createOrder')->willReturn($FinalOrder);
        $ProcessOrder->expects(self::once())->method('delete');
        $Handler = new TestableHandler();
        $Handler->setResolvedOrderInProcess($ProcessOrder);

        try {
            $Session->setValue($Users, $Users->getNobody());
            $this->withHandler($Handler, static function () use ($Rewrite): void {
                (new ReflectionMethod(EventHandling::class, 'handleRequest'))
                    ->invoke(null, $Rewrite, 'checkout/Order/process-route-order');
            });
        } finally {
            $Session->setValue($Users, $originalUser);
        }
    }

    public function testRequestWithoutProjectStopsWithoutChangingSite(): void
    {
        $Rewrite = $this->createMock(Rewrite::class);
        $Rewrite->method('getProject')->willReturn(null);
        $Rewrite->expects(self::never())->method('setSite');

        EventHandling::onRequest($Rewrite, 'checkout');
        self::assertTrue(true);
    }

    public function testSimpleCheckoutIsNotInterceptedByClassicRequestRouting(): void
    {
        $CheckoutSite = $this->createMock(Site::class);
        $CheckoutSite->method('getAttribute')->with('type')->willReturn(
            'quiqqer/order-simple-checkout:types/simpleCheckout'
        );
        $CheckoutSite->expects(self::never())->method('getUrlRewritten');
        $CheckoutSite->expects(self::never())->method('setAttribute');
        $Project = $this->createMock(Project::class);
        $Project->expects(self::exactly(2))->method('getSites')->willReturnCallback(
            static fn(array $query): array => $query['where']['type'] === 'quiqqer/order-simple-checkout:types/simpleCheckout'
                ? [$CheckoutSite]
                : []
        );
        $Rewrite = $this->createMock(Rewrite::class);
        $Rewrite->method('getProject')->willReturn($Project);
        $Rewrite->expects(self::never())->method('setSite');

        (new ReflectionMethod(EventHandling::class, 'handleRequest'))
            ->invoke(null, $Rewrite, 'shop/checkout.html');
    }

    public function testRequestRoutingRejectsUnrelatedPathsAndAcceptsStepWithoutOrder(): void
    {
        $CheckoutSite = $this->createMock(Site::class);
        $CheckoutSite->method('getUrlRewritten')->willReturn('/checkout/');
        $CheckoutSite->expects(self::once())
            ->method('setAttribute')
            ->with('order::step', 'Checkout');
        $Project = $this->createMock(Project::class);
        $Project->method('getSites')->willReturn([$CheckoutSite]);
        $Rewrite = $this->createMock(Rewrite::class);
        $Rewrite->method('getProject')->willReturn($Project);
        $Rewrite->expects(self::once())->method('setSite')->with($CheckoutSite);
        $Method = new ReflectionMethod(EventHandling::class, 'handleRequest');

        $Method->invoke(null, $Rewrite, 'unrelated');
        $Method->invoke(null, $Rewrite, 'prefix/checkout');
        $Method->invoke(null, $Rewrite, 'checkout/Checkout');
    }

    public function testAuthenticatedOrderRouteSetsValidatedStepAndHash(): void
    {
        $Users = \QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $originalUser = $Session->getValue($Users);
        $attributes = [];
        $CheckoutSite = $this->createMock(Site::class);
        $CheckoutSite->method('getUrlRewritten')->willReturn('/checkout/');
        $CheckoutSite->method('setAttribute')->willReturnCallback(
            static function (string $name, mixed $value) use (&$attributes): void {
                $attributes[$name] = $value;
            }
        );
        $Project = $this->createMock(Project::class);
        $Project->method('getSites')->willReturn([$CheckoutSite]);
        $Rewrite = $this->createMock(Rewrite::class);
        $Rewrite->method('getProject')->willReturn($Project);
        $Rewrite->expects(self::once())->method('setSite')->with($CheckoutSite);
        $PriceFactors = $this->createMock(\QUI\ERP\Accounting\PriceFactors\FactorList::class);
        $PriceFactors->method('toArray')->willReturn([]);
        $Articles = $this->createMock(\QUI\ERP\Accounting\ArticleList::class);
        $Articles->method('toArray')->willReturn(['articles' => []]);
        $Articles->method('getPriceFactors')->willReturn($PriceFactors);
        $Currency = $this->createMock(\QUI\ERP\Currency\Currency::class);
        $Order = $this->createMock(Order::class);
        $Order->method('getUUID')->willReturn('authenticated-order');
        $Order->method('getHash')->willReturn('authenticated-order');
        $Order->method('getArticles')->willReturn($Articles);
        $Order->method('getCurrency')->willReturn($Currency);
        $Handler = new TestableHandler();
        $Handler->setResolvedOrder($Order);

        try {
            $Session->setValue($Users, $Users->getSystemUser());
            $this->withHandler($Handler, static function () use ($Rewrite): void {
                (new ReflectionMethod(EventHandling::class, 'handleRequest'))
                    ->invoke(null, $Rewrite, 'checkout/Order/authenticated-order');
            });
        } finally {
            $Session->setValue($Users, $originalUser);
        }

        self::assertSame('Order', $attributes['order::step']);
        self::assertSame('authenticated-order', $attributes['order::hash']);
    }

    public function testPackageSetupIgnoresOtherPackages(): void
    {
        $Package = $this->createMock(\QUI\Package\Package::class);
        $Package->method('getName')->willReturn('vendor/unrelated');

        EventHandling::onPackageSetup($Package);
        self::assertTrue(true);
    }

    public static function mysqlPlatforms(): iterable
    {
        yield 'MySQL' => [new MySQLPlatform()];
        yield 'MariaDB' => [new MariaDBPlatform()];
    }

    #[DataProvider('mysqlPlatforms')]
    public function testHistoryMigrationWidensTextOnceAndPreservesColumnOptions(AbstractMySQLPlatform $Platform): void
    {
        $tableName = \QUI::getDBTableName('orders');
        $Table = new Table($tableName);
        $Table->addColumn('id', 'integer');
        $Table->addColumn('history', 'text', [
            'length' => AbstractMySQLPlatform::LENGTH_LIMIT_TEXT,
            'notnull' => false,
            'default' => null,
            'comment' => 'Order audit history'
        ]);
        $originalOptions = $Table->getColumn('history')->toArray();
        $SchemaManager = $this->createMock(MySQLSchemaManager::class);
        $SchemaManager->method('tablesExist')->with([$tableName])->willReturn(true);
        $SchemaManager->method('introspectTable')->with($tableName)->willReturn($Table);
        $SchemaManager->expects(self::once())->method('alterTable')->willReturnCallback(
            static function (TableDiff $Diff) use ($Table, $Platform, $originalOptions): void {
                $changedColumns = $Diff->getChangedColumns();
                self::assertSame(['history'], array_keys($changedColumns));
                $TargetColumn = $changedColumns['history']->getNewColumn();
                self::assertSame(4294967295, $TargetColumn->getLength());
                $expectedOptions = $originalOptions;
                $expectedOptions['length'] = 4294967295;
                self::assertSame($expectedOptions, $TargetColumn->toArray());
                self::assertStringContainsString('LONGTEXT', implode("\n", $Platform->getAlterTableSQL($Diff)));
                self::assertSame([], $Diff->getAddedColumns());
                self::assertSame([], $Diff->getDroppedColumns());
                $Table->modifyColumn('history', ['length' => 4294967295]);
            }
        );
        $Connection = $this->createMock(Connection::class);
        $Connection->method('getDatabasePlatform')->willReturn($Platform);
        $Connection->method('createSchemaManager')->willReturn($SchemaManager);

        $this->withDatabaseConnection($Connection, static function () use ($tableName): void {
            $Migration = new ReflectionMethod(EventHandling::class, 'migrateOrderHistoryColumn');
            $Migration->invoke(null, $tableName);
            $Migration->invoke(null, $tableName);
        });
    }

    public static function unchangedHistoryColumns(): iterable
    {
        yield 'missing column' => [null, null];
        yield 'unlimited longtext' => ['text', null];
        yield 'explicit longtext' => ['text', 4294967295];
        yield 'mediumtext' => ['text', AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMTEXT];
        yield 'tinytext' => ['text', AbstractMySQLPlatform::LENGTH_LIMIT_TINYTEXT];
        yield 'other type' => ['string', AbstractMySQLPlatform::LENGTH_LIMIT_TEXT];
    }

    #[DataProvider('unchangedHistoryColumns')]
    public function testHistoryMigrationLeavesOtherColumnsUnchanged(?string $type, ?int $length): void
    {
        $Table = new Table('orders_history_skip');

        if ($type !== null) {
            $Table->addColumn('history', $type, ['length' => $length]);
        }

        $SchemaManager = $this->createMock(MySQLSchemaManager::class);
        $SchemaManager->method('tablesExist')->willReturn(true);
        $SchemaManager->method('introspectTable')->willReturn($Table);
        $SchemaManager->expects(self::never())->method('alterTable');
        $Connection = $this->createMock(Connection::class);
        $Connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $Connection->method('createSchemaManager')->willReturn($SchemaManager);

        $this->withDatabaseConnection($Connection, static function (): void {
            (new ReflectionMethod(EventHandling::class, 'migrateOrderHistoryColumn'))
                ->invoke(null, 'orders_history_skip');
        });
    }

    public function testHistoryMigrationSkipsMissingTables(): void
    {
        $SchemaManager = $this->createMock(MySQLSchemaManager::class);
        $SchemaManager->method('tablesExist')->willReturn(false);
        $SchemaManager->expects(self::never())->method('introspectTable');
        $SchemaManager->expects(self::never())->method('alterTable');
        $Connection = $this->createMock(Connection::class);
        $Connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $Connection->method('createSchemaManager')->willReturn($SchemaManager);

        $this->withDatabaseConnection($Connection, static function (): void {
            (new ReflectionMethod(EventHandling::class, 'migrateOrderHistoryColumn'))
                ->invoke(null, 'missing_orders');
        });
    }

    public function testHistoryMigrationLeavesPostgreSqlUnchanged(): void
    {
        $Connection = $this->createMock(Connection::class);
        $Connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $Connection->expects(self::never())->method('createSchemaManager');

        $this->withDatabaseConnection($Connection, static function (): void {
            (new ReflectionMethod(EventHandling::class, 'migrateOrderHistoryColumn'))
                ->invoke(null, 'orders');
        });
    }

    public function testDetailEquipmentFallbackCreatesProductLink(): void
    {
        $Product = $this->createMock(AbstractType::class);
        $Product->method('getUrl')->willReturn('/products/example');
        $Collector = new Collector();

        EventHandling::onDetailEquipmentButtons($Collector, $Product);

        self::assertSame(
            '<a href="/products/example"><span class="fa fa-chevron-right"></span></a>',
            $Collector->getContent()
        );
    }

    public function testTemplateEventsAppendConfigurationAndFrontendScripts(): void
    {
        $Template = $this->createMock(Template::class);
        $Template->expects(self::once())
            ->method('extendHeader')
            ->with(self::stringContains('window.QUIQQER_ORDER_ORDER_PROCESS_MERGE'));
        $Template->expects(self::once())
            ->method('extendFooter')
            ->with(self::callback(
                static fn(string $html): bool => str_contains($html, 'checkoutBootstrap.js')
                    && str_contains($html, 'dataLayerTracking.js')
            ));

        EventHandling::onTemplateGetHeader($Template);
        EventHandling::onTemplateEnd(new Collector(), $Template);
    }

    public function testStatusNotificationStopsForManualSaveOrDisabledStatus(): void
    {
        $ManualOrder = $this->createMock(AbstractOrder::class);
        $ManualOrder->method('getAttribute')->with('userSave')->willReturn(true);
        $ManualStatus = $this->createMock(Status::class);
        $ManualStatus->expects(self::never())->method('isAutoNotification');

        EventHandling::onQuiqqerOrderProcessStatusChange($ManualOrder, $ManualStatus);

        $AutomaticOrder = $this->createMock(AbstractOrder::class);
        $AutomaticOrder->method('getAttribute')->with('userSave')->willReturn(false);
        $DisabledStatus = $this->createMock(Status::class);
        $DisabledStatus->expects(self::once())->method('isAutoNotification')->willReturn(false);

        EventHandling::onQuiqqerOrderProcessStatusChange($AutomaticOrder, $DisabledStatus);
        self::assertTrue(true);
    }

    public function testSalesOrderWithoutLinkedOrderStopsImmediately(): void
    {
        $SalesOrder = $this->createMock(\QUI\ERP\SalesOrders\SalesOrder::class);
        $SalesOrder->method('getData')->with('orderId')->willReturn(null);
        $SalesOrder->expects(self::never())->method('getShipping');

        EventHandling::onQuiqqerSalesOrdersSaveEnd($SalesOrder);
        self::assertTrue(true);
    }

    public function testTransactionEventsDelegateToResolvedOrder(): void
    {
        $Order = $this->createMock(Order::class);
        $Order->method('getUUID')->willReturn('transaction-order');
        $Order->expects(self::once())->method('addTransaction');
        $Order->expects(self::never())->method('linkTransaction');
        $Order->expects(self::once())->method('calculatePayments');
        $Order->expects(self::once())
            ->method('setAttribute')
            ->with('paid_status', \QUI\ERP\Constants::PAYMENT_STATUS_OPEN);
        $Handler = new TestableHandler();
        $Handler->setResolvedOrder($Order);
        $Transaction = $this->createMock(Transaction::class);
        $Transaction->method('getGlobalProcessId')->willReturn('global-process');
        $Transaction->method('getHash')->willReturn('transaction-order');

        $this->withHandler($Handler, static function () use ($Transaction): void {
            EventHandling::onTransactionCreate($Transaction);
            EventHandling::onTransactionStatusChange($Transaction);
        });
    }

    public function testInvoiceTransactionIsLinkedToOrderFromSameProcess(): void
    {
        $Order = $this->createMock(Order::class);
        $Order->method('getUUID')->willReturn('transaction-order');
        $Order->expects(self::never())->method('addTransaction');
        $Transaction = $this->createMock(Transaction::class);
        $Transaction->method('getGlobalProcessId')->willReturn('global-process');
        $Transaction->method('getHash')->willReturn('invoice-draft');
        $Order->expects(self::once())->method('linkTransaction')->with($Transaction);
        $Handler = new TestableHandler();
        $Handler->setResolvedOrder($Order);

        $this->withHandler($Handler, static function () use ($Transaction): void {
            EventHandling::onTransactionCreate($Transaction);
        });
    }

    public function testStatusChangeNotificationDelegatesForAutomaticStatus(): void
    {
        $Customer = $this->createMock(\QUI\ERP\User::class);
        $Customer->method('getAttribute')->with('email')->willReturn('');
        $Customer->method('getUUID')->willReturn('event-customer');
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getAttribute')->with('userSave')->willReturn(false);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getPrefixedNumber')->willReturn('ORDER-EVENT');
        $Status = $this->createMock(Status::class);
        $Status->method('isAutoNotification')->willReturn(true);
        $Status->method('getId')->willReturn(1);

        EventHandling::onQuiqqerOrderProcessStatusChange($Order, $Status);
        self::assertTrue(true);
    }

    public function testUserCommentsIncludeExistingOrderCommentsAndCreationEntry(): void
    {
        $OrderComments = new Comments();
        $OrderComments->addComment('Existing comment');
        $Order = $this->createMock(Order::class);
        $Order->method('getComments')->willReturn($OrderComments);
        $Order->method('getUUID')->willReturn('comment-order');
        $Order->method('getAttribute')->with('c_date')->willReturn('2024-01-02 03:04:05');
        $Handler = new TestableHandler();
        $Handler->setResolvedUserOrders([$Order]);
        $Target = new Comments();
        $User = $this->createMock(\QUI\Users\User::class);

        $this->withHandler($Handler, static function () use ($Target, $User): void {
            EventHandling::onQuiqqerErpGetCommentsByUser(
                $User,
                $Target
            );
        });

        self::assertCount(2, $Target->toArray());
    }

    public function testSalesOrderSynchronizesTrackingShippingAndStatus(): void
    {
        $Shipping = $this->createMock(\QUI\ERP\Shipping\Api\ShippingInterface::class);
        $ShippingStatus = $this->createMock(\QUI\ERP\Shipping\ShippingStatus\Status::class);
        $Order = $this->createMock(Order::class);
        $Order->method('getDataEntry')->with('shippingTracking')->willReturn(
            '{"number":"old","type":"parcel"}'
        );
        $Order->expects(self::once())->method('setData')->with(
            'shippingTracking',
            ['number' => 'new', 'type' => 'parcel']
        );
        $Order->expects(self::once())->method('setShipping')->with($Shipping);
        $Order->expects(self::once())->method('update');
        $Order->expects(self::once())->method('setShippingStatus')->with($ShippingStatus);
        $Handler = new TestableHandler();
        $Handler->setResolvedOrder($Order);
        $SalesOrder = $this->createMock(\QUI\ERP\SalesOrders\SalesOrder::class);
        $SalesOrder->method('getData')->willReturnCallback(
            static fn(string $key): mixed => match ($key) {
                'orderId' => 'event-order',
                'shippingTracking' => '{"number":"new","type":"parcel"}',
                default => null
            }
        );
        $SalesOrder->method('getShipping')->willReturn($Shipping);
        $SalesOrder->method('getShippingStatus')->willReturn($ShippingStatus);

        $this->withHandler($Handler, static function () use ($SalesOrder): void {
            EventHandling::onQuiqqerSalesOrdersSaveEnd($SalesOrder);
        });
    }

    private function withDatabaseConnection(Connection $Connection, callable $callback): void
    {
        $Property = new ReflectionProperty(\QUI::class, 'QueryBuilder');
        $original = $Property->getValue();
        $Property->setValue(null, $Connection);

        try {
            $callback();
        } finally {
            $Property->setValue(null, $original);
        }
    }

    private function withHandler(TestableHandler $Handler, callable $callback): void
    {
        $Instances = new ReflectionProperty(Singleton::class, 'instances');
        $original = $Instances->getValue();
        $instances = $original;
        $instances[\QUI\ERP\Order\Handler::class] = $Handler;
        $Instances->setValue(null, $instances);

        try {
            $callback();
        } finally {
            $Instances->setValue(null, $original);
        }
    }
}
