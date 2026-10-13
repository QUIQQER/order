<?php

namespace QUITests\ERP\Order;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Table;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\AI\MCP\Server;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Address;
use QUI\ERP\Comments;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Currency\Handler as CurrencyHandler;
use QUI\ERP\Order\Factory;
use QUI\ERP\Order\Handler;
use QUI\ERP\Order\McpProvider;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\User;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\Permissions\Manager;
use QUI\Utils\Singleton;
use ReflectionProperty;

class McpProviderTest extends TestCase
{
    private Connection $originalConnection;
    private Connection $connection;
    private mixed $originalRequestUser;
    private ?Manager $originalRights;
    private mixed $originalDefaultCurrency;
    /** @var array<array-key, mixed> */
    private array $originalInstances;
    private Handler & MockObject $Handler;
    private Factory & MockObject $Factory;

    protected function setUp(): void
    {
        $this->originalConnection = QUI::getDataBaseConnection();
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true
        ]);
        (new ReflectionProperty(QUI::class, 'QueryBuilder'))->setValue(null, $this->connection);
        $this->originalRequestUser = (new ReflectionProperty(Server::class, 'RequestUser'))->getValue();
        $this->originalRights = QUI::$Rights;
        $DefaultCurrency = new ReflectionProperty(CurrencyHandler::class, 'Default');
        $this->originalDefaultCurrency = $DefaultCurrency->getValue();
        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('EUR');
        $DefaultCurrency->setValue(null, $Currency);
        $this->setRequestUser(QUI::getUsers()->getSystemUser());

        $this->Handler = $this->createMock(Handler::class);
        $this->Handler->method('table')->willReturn('mcp_orders');
        $this->Handler->method('tableOrderProcess')->willReturn('mcp_drafts');
        $this->Factory = $this->createMock(Factory::class);
        $Instances = new ReflectionProperty(Singleton::class, 'instances');
        $this->originalInstances = $Instances->getValue();
        $instances = $this->originalInstances;
        $instances[Handler::class] = $this->Handler;
        $instances[Factory::class] = $this->Factory;
        $instances[Payments::class] = $this->createMock(Payments::class);
        $Instances->setValue(null, $instances);

        foreach (['mcp_orders', 'mcp_drafts'] as $tableName) {
            $Table = new Table($tableName);
            $Table->addColumn('id', 'integer');
            $Table->setPrimaryKey(['id']);

            foreach (['hash', 'id_str', 'customerId', 'currency', 'c_date', 'order_id', 'order_process_id'] as $field) {
                $Table->addColumn($field, 'string', ['notnull' => false]);
            }

            foreach (['status', 'paid_status'] as $field) {
                $Table->addColumn($field, 'integer', ['default' => 0]);
            }

            $this->connection->createSchemaManager()->createTable($Table);
        }
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Server::class, 'RequestUser'))->setValue(null, $this->originalRequestUser);
        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, $this->originalInstances);
        (new ReflectionProperty(QUI::class, 'QueryBuilder'))->setValue(null, $this->originalConnection);
        QUI::$Rights = $this->originalRights;
        (new ReflectionProperty(CurrencyHandler::class, 'Default'))->setValue(null, $this->originalDefaultCurrency);
        $this->connection->close();
    }

    public function testRegistrationAndRevokedPermissionAtEveryCallback(): void
    {
        $tools = $this->getTools();
        self::assertSame([
            'order_get',
            'order_search',
            'order_draft_create',
            'order_draft_update',
            'order_draft_review',
            'order_draft_submit',
            'order_reorder',
            'order_update'
        ], array_keys($tools));

        $this->setRestrictedUser([]);
        self::assertSame([], $this->getTools());
        $arguments = [
            'order_get' => ['1'],
            'order_search' => [],
            'order_draft_create' => [[]],
            'order_draft_update' => ['1', []],
            'order_draft_review' => ['1'],
            'order_draft_submit' => ['draft-hash', 20.0, 'EUR'],
            'order_reorder' => ['1'],
            'order_update' => ['1', []]
        ];
        $this->Factory->expects(self::never())->method('createOrderInProcess');
        $this->Handler->expects(self::never())->method('get');

        foreach ($tools as $name => $tool) {
            self::assertFalse($tool['inputSchema']['additionalProperties']);
            self::assertNotEmpty($tool['description']);
            self::assertTrue($tool['handler'](...$arguments[$name])->isError);
        }
    }

    public function testWriteToolsRequireDomainPermissions(): void
    {
        $this->setRestrictedUser(['quiqqer.order.mcp' => true]);
        $tools = $this->getTools();
        $this->Factory->expects(self::never())->method('createOrderInProcess');

        self::assertTrue($tools['order_draft_create']['handler']([])->isError);
        self::assertTrue($tools['order_draft_update']['handler']('1', [])->isError);
        self::assertTrue($tools['order_draft_review']['handler']('1')->isError);
        self::assertTrue($tools['order_draft_submit']['handler']('draft-hash', 20.0, 'EUR')->isError);
        self::assertTrue($tools['order_reorder']['handler']('1')->isError);
        self::assertTrue($tools['order_update']['handler']('1', [])->isError);
    }

    public function testSearchCountPaginationFiltersAndInjectionResistance(): void
    {
        foreach ([1, 2, 3] as $id) {
            $this->connection->insert('mcp_orders', [
                'id' => $id,
                'hash' => 'hash-' . $id,
                'id_str' => 'ORD-' . $id,
                'customerId' => $id === 3 ? 'other' : 'customer',
                'status' => 2
            ]);
        }

        $search = $this->getTools()['order_search']['handler'];
        $result = $search('ORD-', 1, 1, false, 'customer', 2, 'id ASC');
        self::assertSame(2, $result['count']);
        self::assertSame([2], array_column($result['orders'], 'id'));
        self::assertSame(0, $search("' OR 1=1 --")['count']);
        self::assertTrue($search('', 20, 0, false, '', null, 'id; DROP TABLE mcp_orders')->isError);
        $bounded = $search('', 0, -1);
        self::assertSame(1, $bounded['limit']);
        self::assertSame(0, $bounded['offset']);
        self::assertSame(100, $search('', 200)['limit']);

        $this->connection->insert('mcp_drafts', ['id' => 1, 'order_id' => null]);
        $this->connection->insert('mcp_drafts', ['id' => 2, 'order_id' => '3']);
        self::assertSame(1, $search('', 20, 0, true)['count']);
    }

    public function testReadResolvesNumericPrefixedAndHashIdentifiersWithoutDraftCollision(): void
    {
        $this->connection->insert('mcp_orders', ['id' => 12, 'id_str' => 'ORD-12', 'hash' => 'order-hash']);
        $this->connection->insert('mcp_drafts', ['id' => 12, 'hash' => 'draft-hash']);
        $Order = $this->makeOrder(false);
        $this->Handler->method('get')->with(12)->willReturn($Order);
        $this->Handler->method('getOrderInProcess')->with(12)->willReturn($this->makeOrder(true));
        $read = $this->getTools()['order_get']['handler'];

        foreach (['12', 'ORD-12', 'order-hash'] as $identifier) {
            $result = $read($identifier);
            self::assertFalse($result['draft']);
            self::assertSame('snapshot article', $result['articles'][0]['title']);
            self::assertArrayNotHasKey('customData', $result['articles'][0]);
            self::assertArrayNotHasKey('articles', $read($identifier, false, false));
        }

        self::assertTrue($read('12', true)['draft']);
        self::assertTrue($read('draft-hash')->isError);
        self::assertTrue($read(' ')->isError);
    }

    public function testCreateDraftAppliesDataAndSavesAsRequestUser(): void
    {
        $Draft = $this->makeOrder(true);
        $Draft->expects(self::once())->method('setInvoiceAddress')->with(['city' => 'Aachen']);
        $Draft->expects(self::once())->method('removeDeliveryAddress');
        $Draft->expects(self::once())->method('clearArticles');
        $Draft->expects(self::once())->method('addArticle')->with(self::callback(
            static fn(Article $Article): bool => $Article->getTitle() === 'new article' && $Article->getQuantity() === 2
        ));
        $Draft->expects(self::once())->method('save')->with(Server::getRequestUser());
        $this->Factory->expects(self::once())->method('createOrderInProcess')
            ->with(Server::getRequestUser())->willReturn($Draft);
        $result = $this->getTools()['order_draft_create']['handler']([
            'addressInvoice' => ['city' => 'Aachen'],
            'addressDelivery' => [],
            'articles' => [
                ['title' => 'new article', 'unitPrice' => 10, 'quantity' => 2]
            ]
        ]);

        self::assertIsArray($result);
        self::assertTrue($result['draft']);
    }

    #[DataProvider('invalidData')]
    public function testInvalidPayloadDoesNotCreateDraft(array $data): void
    {
        $this->Factory->expects(self::never())->method('createOrderInProcess');
        self::assertTrue($this->getTools()['order_draft_create']['handler']($data)->isError);
    }

    public static function invalidData(): array
    {
        return [
            [['paid_status' => 1]],
            [['articles' => [['title' => 'x', 'unitPrice' => 1, 'quantity' => 0]]]],
            [['articles' => [['title' => 'x', 'unitPrice' => 1, 'quantity' => 1, 'class' => 'Injected']]]],
            [['articles' => [['title' => 'x', 'quantity' => 1]]]],
            [['articles' => [false]]],
            [['addressInvoice' => ['unknown' => 'x']]],
            [['customerId' => false]],
            [['comment' => '']],
            [['status' => -1]]
        ];
    }

    public function testDraftCannotBeEditedAfterSubmission(): void
    {
        $Draft = $this->makeOrder(true, false, false, '99');
        $this->connection->insert('mcp_drafts', ['id' => 12, 'hash' => 'draft-hash']);
        $this->Handler->method('getOrderInProcess')->willReturn($Draft);
        $Draft->expects(self::never())->method('save');
        self::assertTrue($this->getTools()['order_draft_update']['handler']('12', ['comment' => 'x'])->isError);
    }

    public function testForeignDraftIsRejectedBeforeMutation(): void
    {
        $this->setRestrictedUser(['quiqqer.order.mcp' => true, 'quiqqer.order.update' => true]);
        $Draft = $this->makeOrder(true);
        $Creator = $this->createMock(UserInterface::class);
        $Creator->method('getUUID')->willReturn('another-user');
        $Draft->method('getCreateUser')->willReturn($Creator);
        $Draft->expects(self::never())->method('addComment');
        $Draft->expects(self::never())->method('save');
        $this->connection->insert('mcp_drafts', ['id' => 12]);
        $this->Handler->method('getOrderInProcess')->willReturn($Draft);
        self::assertTrue($this->getTools()['order_draft_update']['handler']('12', ['comment' => 'x'])->isError);
    }

    public function testOwnerCanUpdateDraftWithDomainPermission(): void
    {
        $this->setRestrictedUser(['quiqqer.order.mcp' => true, 'quiqqer.order.update' => true]);
        $Draft = $this->makeOrder(true);
        $Draft->method('getCreateUser')->willReturn(Server::getRequestUser());
        $Draft->expects(self::once())->method('addComment')->with('reviewed draft');
        $Draft->expects(self::once())->method('save')->with(Server::getRequestUser());
        $this->connection->insert('mcp_drafts', ['id' => 12]);
        $this->Handler->method('getOrderInProcess')->willReturn($Draft);

        self::assertIsArray($this->getTools()['order_draft_update']['handler']('12', ['comment' => 'reviewed draft']));
    }

    #[DataProvider('lockedOrders')]
    public function testCompletedAndInvoicedOrdersRejectCommercialEdits(bool $successful, bool $posted): void
    {
        $Order = $this->makeOrder(false, $successful, $posted);
        $this->connection->insert('mcp_orders', ['id' => 12]);
        $this->Handler->method('get')->willReturn($Order);
        $Order->expects(self::never())->method('setInvoiceAddress');
        $Order->expects(self::once())->method('addComment')->with('reviewed');
        $Order->expects(self::once())->method('save')->with(Server::getRequestUser());
        $update = $this->getTools()['order_update']['handler'];

        self::assertTrue($update('12', ['addressInvoice' => ['city' => 'x']])->isError);
        self::assertIsArray($update('12', ['comment' => 'reviewed']));
    }

    public static function lockedOrders(): array
    {
        return [[true, false], [false, true]];
    }

    public function testReorderCreatesFreshDraftAndCopiesOnlySelectedArticleFields(): void
    {
        $Source = $this->makeOrder(false, true, true);
        $Draft = $this->makeOrder(true);
        $this->connection->insert('mcp_orders', ['id' => 12]);
        $this->Handler->method('get')->willReturn($Source);
        $this->Factory->expects(self::once())->method('createOrderInProcess')->willReturn($Draft);
        $Draft->expects(self::once())->method('setCustomer')->with($Source->getCustomer());
        $Draft->expects(self::once())->method('setCurrency')->with($Source->getCurrency());
        $Draft->expects(self::once())->method('addArticle')->with(self::callback(
            static fn(Article $Article): bool => $Article->getTitle() === 'snapshot article'
                && $Article->getCustomData() === []
                && $Article->getDiscount() === null
        ));
        $Draft->expects(self::never())->method('setPayment');
        $Draft->expects(self::never())->method('setSuccessfulStatus');
        $Draft->expects(self::once())->method('save')->with(Server::getRequestUser());
        $result = $this->getTools()['order_reorder']['handler']('12');

        self::assertIsArray($result);
        self::assertTrue($result['draft']);
        self::assertFalse($result['successful']);
        self::assertFalse($result['invoiced']);
        self::assertTrue($result['requiresReview']);
    }

    public function testFailedDraftSaveRollsBackCreatedDatabaseRow(): void
    {
        $Draft = $this->makeOrder(true);
        $Draft->method('save')->willThrowException(new QUI\Exception('save failed'));
        $this->Factory->method('createOrderInProcess')->willReturnCallback(function () use ($Draft): OrderInProcess {
            $this->connection->insert('mcp_drafts', ['id' => 12]);

            return $Draft;
        });

        self::assertTrue($this->getTools()['order_draft_create']['handler']([])->isError);
        self::assertSame(0, (int)$this->connection->fetchOne('SELECT COUNT(*) FROM mcp_drafts'));
    }

    public function testReviewRefreshesDraftAndReturnsValidationAndPaymentOptions(): void
    {
        $Draft = $this->makeOrder(true);
        $Draft->expects(self::once())->method('recalculate');
        $this->connection->insert('mcp_drafts', ['id' => 12, 'hash' => 'draft-hash']);
        $this->Handler->method('getOrderInProcess')->willReturn($Draft);
        $result = $this->getTools()['order_draft_review']['handler']('draft-hash');

        self::assertIsArray($result);
        self::assertTrue($result['readyToSubmit']);
        self::assertSame([], $result['validationErrors']);
        self::assertSame([], $result['paymentMethods']);
        self::assertSame(20.0, $result['totals']['sum']);
    }

    public function testSubmitCreatesRealOrderAndRetryDoesNotSubmitAgain(): void
    {
        $Draft = $this->makeOrder(true);
        $Order = $this->makeOrder(false, uuid: 'draft-hash');
        $this->connection->insert('mcp_drafts', ['id' => 12, 'hash' => 'draft-hash']);
        $this->Handler->method('getOrderInProcess')->willReturn($Draft);
        $this->Handler->method('get')->willReturn($Order);
        $Draft->expects(self::once())->method('createOrder')->willReturnCallback(
            function (UserInterface $User, callable $BeforeCreate) use ($Draft, $Order): Order {
                self::assertSame(Server::getRequestUser(), $User);
                $BeforeCreate($Draft);
                $this->connection->insert('mcp_orders', [
                    'id' => 12,
                    'hash' => 'draft-hash',
                    'order_process_id' => 'draft-hash'
                ]);
                $this->connection->delete('mcp_drafts', ['hash' => 'draft-hash']);

                return $Order;
            }
        );
        $submit = $this->getTools()['order_draft_submit']['handler'];
        $result = $submit('draft-hash', 20.0, 'EUR');

        self::assertIsArray($result);
        self::assertSame('draft-hash', $result['uuid']);
        self::assertFalse($result['draft']);
        self::assertFalse($result['alreadySubmitted']);
        self::assertTrue($submit('draft-hash', 20.0, 'EUR')['alreadySubmitted']);
        self::assertSame(1, (int)$this->connection->fetchOne('SELECT COUNT(*) FROM mcp_orders'));
    }

    #[DataProvider('invalidSubmissions')]
    public function testSubmitRejectsInvalidOrderDataBeforeCreation(
        array $address,
        array $articles,
        string $identifier
    ): void {
        $Draft = $this->makeOrder(true, address: $address, articles: $articles);
        $Draft->expects(self::never())->method('createOrder');
        $this->connection->insert('mcp_drafts', ['id' => 12, 'hash' => 'draft-hash']);
        $this->Handler->method('getOrderInProcess')->willReturn($Draft);

        self::assertTrue($this->getTools()['order_draft_submit']['handler']($identifier, 20.0, 'EUR')->isError);
    }

    public static function invalidSubmissions(): array
    {
        $address = [
            'firstname' => 'Test',
            'lastname' => 'Customer',
            'street_no' => 'Test Street 1',
            'city' => 'Aachen',
            'country' => 'DE'
        ];
        $article = ['id' => 1, 'title' => 'product', 'quantity' => 1, 'unitPrice' => 20];

        return [
            'missing address' => [[], [$article], 'draft-hash'],
            'empty order' => [$address, [], 'draft-hash'],
            'zero quantity' => [$address, [array_replace($article, ['quantity' => 0])], 'draft-hash'],
            'numeric draft id' => [$address, [$article], '12']
        ];
    }

    #[DataProvider('changedSubmissions')]
    public function testSubmissionChecksFinalRecalculationBeforeCreatingOrder(
        float $expectedTotal,
        string $currency,
        array $recalculatedArticles
    ): void {
        $Draft = $this->makeOrder(true);
        $Recalculated = $this->makeOrder(true, articles: $recalculatedArticles);
        $this->connection->insert('mcp_drafts', ['id' => 12, 'hash' => 'draft-hash']);
        $this->Handler->method('getOrderInProcess')->willReturn($Draft);
        $Draft->method('createOrder')->willReturnCallback(
            function (UserInterface $User, callable $BeforeCreate) use ($Recalculated): Order {
                $BeforeCreate($Recalculated);
                self::fail('Changed order data should stop creation.');
            }
        );

        self::assertTrue($this->getTools()['order_draft_submit']['handler'](
            'draft-hash',
            $expectedTotal,
            $currency
        )->isError);
        self::assertSame(0, (int)$this->connection->fetchOne('SELECT COUNT(*) FROM mcp_orders'));
    }

    public static function changedSubmissions(): array
    {
        $article = ['title' => 'snapshot article', 'quantity' => 2, 'unitPrice' => 10];

        return [
            'price changed' => [19.0, 'EUR', [$article]],
            'currency changed' => [20.0, 'USD', [$article]],
            'quantity changed' => [20.0, 'EUR', [array_replace($article, ['quantity' => 1])]],
            'product removed' => [20.0, 'EUR', []]
        ];
    }

    public function testSubmitCannotReturnAnotherCreatorsOrderOnRetry(): void
    {
        $this->setRestrictedUser(['quiqqer.order.mcp' => true, 'quiqqer.order.create' => true]);
        $Order = $this->makeOrder(false, uuid: 'draft-hash');
        $Creator = $this->createMock(UserInterface::class);
        $Creator->method('getUUID')->willReturn('another-user');
        $Order->method('getCreateUser')->willReturn($Creator);
        $this->connection->insert('mcp_orders', [
            'id' => 12,
            'hash' => 'draft-hash',
            'order_process_id' => 'draft-hash'
        ]);
        $this->Handler->method('get')->willReturn($Order);
        $this->Handler->expects(self::never())->method('getOrderInProcess');

        self::assertTrue($this->getTools()['order_draft_submit']['handler']('draft-hash', 20.0, 'EUR')->isError);
    }

    public function testDraftPaymentSelectionChecksEligibility(): void
    {
        $Draft = $this->makeOrder(true);
        $Payment = $this->createMock(Payment::class);
        $Payment->method('canUsedBy')->willReturn(false);
        Payments::getInstance()->method('getPayment')->with(5)->willReturn($Payment);
        $this->Factory->method('createOrderInProcess')->willReturn($Draft);
        $Draft->expects(self::never())->method('setPayment');
        $Draft->expects(self::never())->method('save');

        self::assertTrue($this->getTools()['order_draft_create']['handler'](['paymentId' => 5])->isError);
    }

    public function testDraftPaymentSelectionAndReviewExposeOnlyEligibleMethods(): void
    {
        $Draft = $this->makeOrder(true);
        $Allowed = $this->createMock(Payment::class);
        $Allowed->method('getId')->willReturn(5);
        $Allowed->method('getTitle')->willReturn('Bank transfer');
        $Allowed->method('getDescription')->willReturn('Pay after ordering.');
        $Allowed->method('canUsedBy')->willReturn(true);
        $Allowed->method('isCurrencySupported')->willReturn(true);
        $Allowed->method('canUsedInOrder')->willReturn(true);
        $Unsupported = $this->createMock(Payment::class);
        $Unsupported->method('canUsedBy')->willReturn(true);
        $Unsupported->method('isCurrencySupported')->willReturn(false);
        $Payments = Payments::getInstance();
        $Payments->method('getPayment')->with(5)->willReturn($Allowed);
        $Payments->method('getUserPayments')->willReturn([$Allowed, $Unsupported]);
        $this->Factory->method('createOrderInProcess')->willReturn($Draft);
        $Draft->expects(self::once())->method('setPayment')->with(5);
        $Draft->expects(self::once())->method('save')->with(Server::getRequestUser());

        self::assertIsArray($this->getTools()['order_draft_create']['handler'](['paymentId' => 5]));
        $this->connection->insert('mcp_drafts', ['id' => 12, 'hash' => 'draft-hash']);
        $this->Handler->method('getOrderInProcess')->willReturn($Draft);
        $review = $this->getTools()['order_draft_review']['handler']('draft-hash');

        self::assertSame([5], array_column($review['paymentMethods'], 'id'));
    }

    private function makeOrder(
        bool $draft,
        bool $successful = false,
        bool $posted = false,
        ?string $orderId = null,
        ?string $uuid = null,
        ?array $address = null,
        ?array $articles = null
    ): (Order & MockObject) | (OrderInProcess & MockObject) {
        $Order = $this->createMock($draft ? OrderInProcess::class : Order::class);
        $Order->method('getId')->willReturn(12);
        $Order->method('getUUID')->willReturn($uuid ?? ($draft ? 'draft-hash' : 'order-hash'));
        $Order->method('getPrefixedNumber')->willReturn('ORD-12');
        $Order->method('getCreateDate')->willReturn('2026-10-13 12:00:00');
        $Order->method('isSuccessful')->willReturn((int)$successful);
        $Order->method('isPosted')->willReturn($posted);
        $Order->method('getProcessingStatus')->willReturn(null);
        $Order->method('getInvoiceAddress')->willReturn(new Address($address ?? [
            'firstname' => 'Test',
            'lastname' => 'Customer',
            'street_no' => 'Test Street 1',
            'city' => 'Aachen',
            'country' => 'DE'
        ]));
        $Order->method('hasDeliveryAddress')->willReturn(false);
        $Order->method('getComments')->willReturn($this->createMock(Comments::class));
        $Customer = $this->createMock(User::class);
        $Customer->method('getUUID')->willReturn('customer');
        $Order->method('getCustomer')->willReturn($Customer);
        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('EUR');
        $Currency->method('getPrecision')->willReturn(2);
        $Order->method('getCurrency')->willReturn($Currency);
        $Articles = $this->createMock(ArticleList::class);
        $Articles->method('calc')->willReturnSelf();
        $Articles->method('getCalculations')->willReturn(['sum' => 20.0]);
        $Articles->method('toArray')->willReturn([
            'articles' => $articles ?? [
                [
                    'title' => 'snapshot article',
                    'unitPrice' => 10,
                    'quantity' => 2,
                    'vat' => 19,
                    'customData' => ['internal' => 'secret'],
                    'discount' => 'historical discount'
                ]
            ]
        ]);
        $Order->method('getArticles')->willReturn($Articles);

        if ($Order instanceof OrderInProcess) {
            $Order->method('getOrderId')->willReturn($orderId);
        }

        return $Order;
    }

    /** @return array<string, array<string, mixed>> */
    private function getTools(): array
    {
        $Builder = new Builder();
        (new McpProvider())->register($Builder);
        $tools = (new ReflectionProperty(Builder::class, 'tools'))->getValue($Builder);

        return array_column($tools, null, 'name');
    }

    private function setRequestUser(UserInterface $User): void
    {
        (new ReflectionProperty(Server::class, 'RequestUser'))->setValue(null, $User);
    }

    /** @param array<string, bool> $permissions */
    private function setRestrictedUser(array $permissions): void
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getUUID')->willReturn('mcp-test-user');
        $User->method('getGroups')->willReturn([]);
        $Rights = $this->createMock(Manager::class);
        $Rights->method('getPermissions')->willReturn($permissions);
        QUI::$Rights = $Rights;
        $this->setRequestUser($User);
    }
}
