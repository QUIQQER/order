<?php

namespace QUITests\ERP\Order;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Calculations;
use QUI\ERP\Accounting\Invoice\Factory as InvoiceFactory;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Customer\Utils as CustomerUtils;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Handler;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\Settings;
use ReflectionMethod;
use ReflectionProperty;

class ManualCalculationTransferTest extends TestCase
{
    /** @var list<array{ReflectionProperty, mixed}> */
    private array $runtime = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->runtime) as [$Property, $value]) {
            $Property->setValue(null, $value);
        }
    }

    #[DataProvider('transferModes')]
    public function testSalesOrderTransferKeepsManualSnapshotAndHistoricalAutomaticAdds(bool $manual): void
    {
        $Source = $this->source(true, $manual);
        $before = $Source->getArticles()->toArray();
        $this->isolateSalesOrderFactory();
        $Target = (new ReflectionMethod(Order::class, 'createSalesOrderDraft'))->invoke($Source, null);
        if ($manual) {
            self::assertSame($before, $Target->getImportedArticles());
        } else {
            self::assertSame([], $Target->getImportedArticles());
            self::assertSame($Source->getArticles()->getArticles()[0], $Target->getArticles()->getArticles()[0]);
            self::assertSame(1.2345, $Target->getArticles()->getPriceFactors()->getFactor(0)->toArray()['sum']);
        }
        if ($manual) {
            self::assertSame($before, $Source->getArticles()->toArray());
        } else {
            self::assertSame(8.4, $Source->getArticles()->toArray()['articles'][0]['calculated']['nettoSum']);
        }
    }

    #[DataProvider('priceModes')]
    public function testInvoiceTransferKeepsManualSnapshotAndHistoricalAutomaticArticlePath(
        bool $isNetto,
        bool $manual
    ): void {
        $Source = $this->source($isNetto, $manual);
        $before = $Source->getArticles()->toArray();
        $Target = $this->getMockBuilder(InvoiceTemporary::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'setCustomer', 'setDeliveryAddress', 'setCurrency', 'importArticles',
                'getArticles', 'save', 'validate', 'getUUID'
            ])
            ->getMock();
        $Target->method('getUUID')->willReturn('fixture-invoice');
        $TargetArticles = $this->createMock(ArticleList::class);
        $Target->method('getArticles')->willReturn($TargetArticles);
        if ($manual) {
            $Target->expects(self::once())->method('importArticles')->with($before);
            $TargetArticles->expects(self::never())->method('addArticle');
        } else {
            $Target->expects(self::never())->method('importArticles');
            $TargetArticles->expects(self::once())->method('addArticle')
                ->with(self::identicalTo($Source->getArticles()->getArticles()[0]));
            $TargetArticles->expects(self::once())->method('setCurrency')->with($Source->getCurrency());
            $TargetArticles->expects(self::once())->method('setUser')->with($Source->getCustomer());
            $TargetArticles->expects(self::once())->method('importPriceFactors')
                ->with(self::identicalTo($Source->getArticles()->getPriceFactors()));
        }
        $Factory = $this->createMock(InvoiceFactory::class);
        $Factory->method('createInvoice')->willReturn($Target);
        $this->singleton(InvoiceFactory::class, $Factory);
        $Handler = $this->createMock(InvoiceHandler::class);
        $Handler->method('getTemporaryInvoice')->willReturn($Target);
        $Handler->method('temporaryInvoiceTable')->willReturn('fixture_invoices');
        $this->singleton(InvoiceHandler::class, $Handler);
        $OrderHandler = $this->createMock(Handler::class);
        $OrderHandler->method('table')->willReturn('fixture_orders');
        $this->singleton(Handler::class, $OrderHandler);
        $Connection = $this->createMock(Connection::class);
        $Connection->method('update')->willReturn(1);
        $this->replace(QUI::class, 'QueryBuilder', $Connection);
        self::assertSame($Target, $Source->createInvoice());
        self::assertSame($manual ? (int)!$isNetto : false, $Target->getAttribute('isbrutto'));
        self::assertSame($before, $Source->getArticles()->toArray());
    }

    /** @return array<string, array{bool}> */
    public static function transferModes(): array
    {
        return ['manual' => [true], 'historical automatic' => [false]];
    }

    /** @return array<string, array{bool, bool}> */
    public static function priceModes(): array
    {
        return [
            'manual net' => [true, true],
            'manual gross' => [false, true],
            'historical automatic' => [true, false]
        ];
    }

    private function source(bool $isNetto = true, bool $manual = true): Order
    {
        $snapshot = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/ManualArticleList.json'), true);
        $snapshot['calculationMode'] = $manual ? 'manual' : 'automatic';
        $snapshot['calculations']['sum'] = 9.990123;
        $snapshot['calculations']['isNetto'] = $isNetto;
        $snapshot['calculations']['currencyData'] = QUI\ERP\Defaults::getCurrency()->toArray();
        $snapshot['priceFactors'] = [[
            'title' => 'Original surcharge',
            'description' => '',
            'sum' => 1.2345,
            'sumFormatted' => '',
            'nettoSum' => 0.9876,
            'nettoSumFormatted' => '',
            'visible' => 1,
            'value' => 0.2469,
            'vat' => 19
        ]];
        $Articles = new ArticleList($snapshot);
        $Source = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getArticles', 'getPriceCalculation', 'getCurrency', 'getCustomer', 'getInvoiceAddress',
                'getDeliveryAddress', 'getPayment', 'getGlobalProcessId', 'getUUID', 'getCreateDate',
                'getReferenceData', 'getPrefixedNumber', 'getTransactions', 'refresh', 'isPosted', 'save', 'updateHistory'
            ])->getMock();
        $Address = $this->createMock(QUI\ERP\Address::class);
        $Address->method('getUUID')->willReturn('fixture-address');
        $Address->method('toJSON')->willReturn('{}');
        $Address->method('getName')->willReturn('Fixture Address');
        $Address->method('getAttribute')->willReturn('Fixture');
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getStandardAddress')->willReturn($Address);
        $Customer->method('getUUID')->willReturn('fixture-customer');
        $Customer->method('getAttributes')->willReturn([]);
        $Source->method('getCustomer')->willReturn($Customer);
        $Source->method('getInvoiceAddress')->willReturn($Address);
        $Source->method('getDeliveryAddress')->willReturn($Address);
        $Source->method('getArticles')->willReturn($Articles);
        $Source->method('getPriceCalculation')->willReturn(new Calculations(
            $Articles->getCalculations(),
            $Articles->getArticles()
        ));
        $Source->method('getCurrency')->willReturn(QUI\ERP\Defaults::getCurrency());
        $Source->method('getPayment')->willReturn($this->createMock(QUI\ERP\Accounting\Payments\Types\Payment::class));
        $Source->method('getGlobalProcessId')->willReturn('fixture-process');
        $Source->method('getUUID')->willReturn('fixture-order');
        $Source->method('getCreateDate')->willReturn('2026-11-19 12:00:00');
        $Source->method('getReferenceData')->willReturn([]);
        $Source->method('getPrefixedNumber')->willReturn('F-1');
        $Source->method('getTransactions')->willReturn([]);
        $Source->method('isPosted')->willReturn(false);
        (new ReflectionProperty(AbstractOrder::class, 'data'))->setValue($Source, []);
        (new ReflectionProperty(AbstractOrder::class, 'History'))->setValue($Source, $this->createMock(QUI\ERP\Comments::class));
        $Settings = $this->createMock(Settings::class);
        $Settings->method('forceCreateInvoice')->willReturn(false);
        $Settings->method('isInvoiceInstalled')->willReturn(true);
        $Settings->method('get')->willReturn(false);
        $this->singleton(Settings::class, $Settings);
        $CustomerUtils = $this->createMock(CustomerUtils::class);
        $CustomerUtils->method('getContactPersonAddress')->willReturn(false);
        $CustomerUtils->method('getPaymentTimeForUser')->willReturn(14);
        $this->singleton(CustomerUtils::class, $CustomerUtils);
        return $Source;
    }

    private function isolateSalesOrderFactory(): void
    {
        // The installed static factory also writes its number-range config; isolate both dependencies.
        QUI::getUserBySession();
        $Connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        foreach (
            [
            QUI\ERP\SalesOrders\Handler::getTableSalesOrders(),
            QUI\ERP\SalesOrders\Handler::getTableSalesOrderDrafts()
            ] as $table
        ) {
            $Schema = new Table($table);
            $Schema->addColumn('id', 'integer', ['autoincrement' => true]);
            $Schema->setPrimaryKey(['id']);
            foreach (
                [
                'id_prefix', 'id_str', 'status', 'c_user', 'c_date', 'e_user', 'e_date', 'editor_id',
                'editor_name', 'hash', 'global_process_id', 'currency', 'currency_data', 'date', 'paid_status'
                ] as $column
            ) {
                $Schema->addColumn($column, 'text', ['notnull' => false]);
            }
            $Connection->createSchemaManager()->createTable($Schema);
        }
        $this->replace(QUI::class, 'QueryBuilder', $Connection);
        $this->replace(QUI::class, 'Events', $this->createMock(QUI\Events\Manager::class));
        $Config = $this->createMock(QUI\Config::class);
        $Config->method('getValue')->willReturn(false);
        $Config->expects(self::once())->method('save');
        $Package = $this->createMock(QUI\Package\Package::class);
        $Package->method('getConfig')->willReturn($Config);
        $Packages = $this->createMock(QUI\Package\Manager::class);
        $Packages->method('getInstalledPackage')->willReturn($Package);
        $this->replace(QUI::class, 'PackageManager', $Packages);
        $Settings = $this->createMock(QUI\ERP\SalesOrders\Settings::class);
        $Settings->method('getDraftsPrefix')->willReturn('F-');
        $this->singleton(QUI\ERP\SalesOrders\Settings::class, $Settings);
        $StatusHandler = $this->createMock(QUI\ERP\SalesOrders\Status\StatusHandler::class);
        $StatusHandler->method('getDraftStatus')->willReturn(false);
        $this->singleton(QUI\ERP\SalesOrders\Status\StatusHandler::class, $StatusHandler);
        $this->replace(QUI\ERP\SalesOrders\Handler::class, 'salesOrders', []);
    }

    private function singleton(string $class, object $instance): void
    {
        $Property = new ReflectionProperty(QUI\Utils\Singleton::class, 'instances');
        $instances = $Property->getValue();
        $instances[$class] = $instance;
        $this->replace(QUI\Utils\Singleton::class, 'instances', $instances);
    }

    private function replace(string $class, string $name, mixed $value): void
    {
        $Property = new ReflectionProperty($class, $name);
        $this->runtime[] = [$Property, $Property->getValue()];
        $Property->setValue(null, $value);
    }
}
