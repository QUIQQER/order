<?php

namespace QUITests\ERP\Order;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\StringType;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Areas\Area;
use QUI\ERP\Areas\Handler as AreasHandler;
use QUI\ERP\Order\Basket\Basket;
use QUI\ERP\Order\Cron\CleanupOrderInProcess;
use QUI\ERP\Order\EventHandling;
use QUI\ERP\Order\Factory;
use QUI\ERP\Order\Handler;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\Order\PaymentReceiver;
use QUI\ERP\Order\Search;
use QUI\System\Console\Tools\MigrationV2;
use ReflectionProperty;
use RuntimeException;
use Throwable;

class OrderLifecycleDatabaseTest extends TestCase
{
    /** @var list<string> */
    private array $orderHashes = [];

    /** @var list<string> */
    private array $orderProcessHashes = [];

    /** @var list<int> */
    private array $basketIds = [];

    private mixed $originalOrderIndex;
    private mixed $originalAutoInvoice;
    private mixed $originalSessionUser;
    private static bool $defaultAreaReady = false;
    private static ?int $createdAreaId = null;
    private static mixed $originalDefaultArea = null;

    protected function setUp(): void
    {
        parent::setUp();

        $Config = QUI::getPackage('quiqqer/order')->getConfig();
        self::assertNotNull($Config);
        $this->originalOrderIndex = $Config->getValue('order', 'orderCurrentIdIndex');
        $this->originalAutoInvoice = $Config->getValue('order', 'autoInvoice');
        $Users = QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $this->originalSessionUser = $Session->getValue($Users);
        $Session->setValue($Users, $Users->getSystemUser());
        self::ensureDefaultArea();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$createdAreaId === null) {
            parent::tearDownAfterClass();

            return;
        }

        $Users = QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $originalSessionUser = $Session->getValue($Users);
        $Session->setValue($Users, $Users->getSystemUser());

        try {
            $Config = QUI::getPackage('quiqqer/tax')->getConfig();

            if ($Config) {
                $Config->set('shop', 'area', self::$originalDefaultArea);
                $Config->save();
            }

            (new AreasHandler())->getChild(self::$createdAreaId)->delete();
        } catch (Throwable) {
            QUI::getDataBaseConnection()->delete(
                QUI::getDBTableName('areas'),
                ['id' => self::$createdAreaId]
            );
        } finally {
            $Session->setValue($Users, $originalSessionUser);
            self::$createdAreaId = null;
            self::$defaultAreaReady = false;
        }

        parent::tearDownAfterClass();
    }

    protected function tearDown(): void
    {
        $Connection = $this->getConnection();
        $Handler = Handler::getInstance();

        foreach (array_unique($this->basketIds) as $basketId) {
            $Connection->delete($Handler->tableBasket(), ['id' => $basketId]);
        }

        foreach (array_unique($this->orderProcessHashes) as $hash) {
            $Connection->delete($Handler->tableOrderProcess(), ['hash' => $hash]);
        }

        foreach (array_unique($this->orderHashes) as $hash) {
            $Connection->delete($Handler->table(), ['hash' => $hash]);
        }

        $Config = QUI::getPackage('quiqqer/order')->getConfig();

        if ($Config) {
            $Config->set('order', 'orderCurrentIdIndex', $this->originalOrderIndex);
            $Config->set('order', 'autoInvoice', $this->originalAutoInvoice);
            $Config->save();
        }

        (new ReflectionProperty(QUI::getUsers(), 'Session'))->setValue(
            QUI::getUsers(),
            $this->originalSessionUser
        );

        parent::tearDown();
    }

    public function testBasketCanBeCreatedPersistedLoadedAndCompleted(): void
    {
        $User = QUI::getUsers()->getSystemUser();
        $Handler = Handler::getInstance();
        $previousBasketId = $this->getMaximumId($Handler->tableBasket());
        $Basket = Factory::getInstance()->createBasket($User);
        $basketId = $this->getMaximumId($Handler->tableBasket());
        self::assertGreaterThan(0, $basketId);
        self::assertGreaterThan($previousBasketId, $basketId);
        $this->basketIds[] = $basketId;
        self::assertSame($basketId, (int)$Handler->getBasketData($basketId, $User)['id']);
        self::assertSame($basketId, $Basket->getId());
        $hash = $this->createMarker('basket');
        $oldEditDate = '2000-01-01 00:00:00';

        $this->getConnection()->update(
            $Handler->tableBasket(),
            ['e_date' => $oldEditDate],
            ['id' => $basketId]
        );

        $Basket->setHash($hash);
        $Basket->save();

        self::assertSame(0, $Basket->count());
        self::assertFalse($Basket->hasOrder());

        $BasketById = $Handler->getBasket($basketId, $User);
        $BasketByHash = $Handler->getBasket($hash, $User);

        self::assertInstanceOf(Basket::class, $BasketById);
        self::assertSame($basketId, $BasketById->getId());
        self::assertSame($hash, $BasketById->getHash());
        self::assertSame($basketId, $BasketByHash->getId());
        self::assertSame($hash, $BasketByHash->getHash());

        $persistedBasket = $this->fetchRow($Handler->tableBasket(), 'id', $basketId);
        self::assertSame((string)$User->getUUID(), $persistedBasket['uid']);
        self::assertSame($hash, $persistedBasket['hash']);
        self::assertNotSame($oldEditDate, $persistedBasket['e_date']);

        $Basket->successful();
        $Basket->save();

        $CompletedBasket = $Handler->getBasketById($basketId, $User);
        self::assertNull($CompletedBasket->getHash());
        self::assertSame(0, $CompletedBasket->count());
    }

    public function testOrderInProcessCanBePersistedAndConvertedExactlyOnce(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $OrderInProcess = Factory::getInstance()->createOrderInProcess($SystemUser);
        $processHash = $OrderInProcess->getUUID();
        $this->orderProcessHashes[] = $processHash;
        $marker = $this->createMarker('process');

        $OrderInProcess->setData('phpunit_flow', $marker);
        $OrderInProcess->addComment('PHPUnit order process comment ' . $marker);
        $OrderInProcess->update($SystemUser);

        $Handler = Handler::getInstance();
        $ReloadedProcess = $Handler->getOrderInProcessByHash($processHash);

        self::assertSame($OrderInProcess->getId(), $ReloadedProcess->getId());
        self::assertSame($marker, $ReloadedProcess->getDataEntry('phpunit_flow'));
        self::assertStringContainsString(
            $marker,
            json_encode($ReloadedProcess->getComments()->toArray(), JSON_THROW_ON_ERROR)
        );

        $persistedProcess = $this->fetchRow($Handler->tableOrderProcess(), 'hash', $processHash);
        self::assertSame($marker, json_decode($persistedProcess['data'], true)['phpunit_flow']);

        $ReloadedProcess->setAttribute('no_invoice_auto_create', true);
        $Order = $ReloadedProcess->createOrder($SystemUser);
        $this->orderHashes[] = $Order->getUUID();

        self::assertInstanceOf(Order::class, $Order);
        self::assertSame($processHash, $Order->getUUID());
        self::assertSame($marker, $Order->getDataEntry('phpunit_flow'));
        self::assertInstanceOf(Order::class, $Handler->getOrderByHash($processHash));
        self::assertNull($this->findRow($Handler->tableOrderProcess(), 'hash', $processHash));

        $persistedOrder = $this->fetchRow($Handler->table(), 'hash', $processHash);
        self::assertSame($marker, json_decode($persistedOrder['data'], true)['phpunit_flow']);

        $SameOrder = $ReloadedProcess->createOrder($SystemUser);
        self::assertSame($Order->getUUID(), $SameOrder->getUUID());
    }

    public function testOrderCanBeFoundChangedAndReloadedThroughPublicApis(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $globalProcessId = $this->createMarker('global');
        $Order = Factory::getInstance()->create($SystemUser, false, null, $globalProcessId);
        $this->orderHashes[] = $Order->getUUID();
        $marker = $this->createMarker('order');

        $Handler = Handler::getInstance();
        self::assertSame($Order->getUUID(), $Handler->getOrderByHash($Order->getUUID())->getUUID());
        self::assertSame($Order->getUUID(), $Handler->getOrderByGlobalProcessId($globalProcessId)->getUUID());
        self::assertSame(
            [$Order->getUUID()],
            array_map(
                static fn (Order $FoundOrder): string => $FoundOrder->getUUID(),
                $Handler->getOrdersByGlobalProcessId($globalProcessId)
            )
        );

        $Order->setData('phpunit_flow', $marker);
        $Order->addComment('PHPUnit order comment ' . $marker);
        $Order->update($SystemUser);

        $ReloadedOrder = $Handler->get($Order->getUUID());
        self::assertSame($marker, $ReloadedOrder->getDataEntry('phpunit_flow'));
        self::assertStringContainsString(
            $marker,
            json_encode($ReloadedOrder->getComments()->toArray(), JSON_THROW_ON_ERROR)
        );

        $persistedOrder = $this->fetchRow($Handler->table(), 'hash', $Order->getUUID());
        self::assertSame($globalProcessId, $persistedOrder['global_process_id']);
        self::assertSame($marker, json_decode($persistedOrder['data'], true)['phpunit_flow']);
    }

    public function testOrdersByUserSupportsCountingSortingAndPagination(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Handler = Handler::getInstance();
        $countBefore = $Handler->countOrdersByUser($SystemUser);
        $createdIds = [];

        for ($i = 0; $i < 3; $i++) {
            $Order = Factory::getInstance()->create($SystemUser, $this->createMarker('user-order'));
            $this->orderHashes[] = $Order->getUUID();
            $Order->setCustomer($SystemUser);
            $Order->update($SystemUser);
            $createdIds[] = $Order->getId();
        }

        self::assertSame($countBefore + 3, $Handler->countOrdersByUser($SystemUser));

        $firstPage = $Handler->getOrdersByUser($SystemUser, [
            'order' => 'id DESC',
            'limit' => '0,2'
        ]);
        $secondPage = $Handler->getOrdersByUser($SystemUser, [
            'order' => 'id DESC',
            'limit' => '2,1'
        ]);
        rsort($createdIds);

        self::assertSame(
            array_slice($createdIds, 0, 2),
            array_map(static fn (Order $Order): int => $Order->getId(), $firstPage)
        );
        self::assertSame(
            [$createdIds[2]],
            array_map(static fn (Order $Order): int => $Order->getId(), $secondPage)
        );
    }

    public function testOrderSearchSupportsTextMatchesSortingPaginationAndCounting(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $searchMarker = $this->createMarker('search');
        $createdIds = [];

        for ($i = 0; $i < 3; $i++) {
            $Order = Factory::getInstance()->create($SystemUser, $searchMarker . '-' . $i);
            $this->orderHashes[] = $Order->getUUID();
            $createdIds[] = $Order->getId();
        }

        $Search = Search::getInstance();
        $Search->clearFilter();
        $Search->order('id DESC');
        $Search->limit(0, 2);
        $Search->setFilter('search', $searchMarker);

        try {
            $orders = $Search->search();
            $gridResult = $Search->searchForGrid();
        } finally {
            $Search->clearFilter();
            $Search->limit(0, 20);
        }

        rsort($createdIds);
        self::assertSame(array_slice($createdIds, 0, 2), array_column($orders, 'id'));
        self::assertSame(3, $gridResult['grid']['total']);
        self::assertCount(2, $gridResult['grid']['data']);
    }

    public function testOrderInProcessCanBeClearedAndReusedWithSameIdentity(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $OrderInProcess = Factory::getInstance()->createOrderInProcess($SystemUser);
        $processHash = $OrderInProcess->getUUID();
        $processId = $OrderInProcess->getId();
        $this->orderProcessHashes[] = $processHash;

        $OrderInProcess->setData('phpunit_flow', $this->createMarker('clear'));
        $OrderInProcess->addComment('PHPUnit comment before clear');
        $OrderInProcess->update($SystemUser);
        $OrderInProcess->clear($SystemUser);

        self::assertSame($processHash, $OrderInProcess->getUUID());
        self::assertSame($processId, $OrderInProcess->getId());
        self::assertNull($OrderInProcess->getDataEntry('phpunit_flow'));
        self::assertSame([], $OrderInProcess->getComments()->toArray());

        $ReloadedProcess = Handler::getInstance()->getOrderInProcessByHash($processHash);
        self::assertSame($processId, $ReloadedProcess->getId());
        self::assertNull($ReloadedProcess->getDataEntry('phpunit_flow'));
        self::assertSame([], $ReloadedProcess->getComments()->toArray());
    }

    public function testCleanupRemovesExpiredAndSingleUseOrderProcesses(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Handler = Handler::getInstance();
        $Connection = $this->getConnection();
        $oldProcess = Factory::getInstance()->createOrderInProcess($SystemUser);
        $singleUseProcess = Factory::getInstance()->createOrderInProcess($SystemUser);
        $recentProcess = Factory::getInstance()->createOrderInProcess($SystemUser);

        foreach ([$oldProcess, $singleUseProcess, $recentProcess] as $OrderInProcess) {
            $this->orderProcessHashes[] = $OrderInProcess->getUUID();
        }

        $Connection->update(
            $Handler->tableOrderProcess(),
            ['c_date' => date('Y-m-d H:i:s', strtotime('-15 days'))],
            ['hash' => $oldProcess->getUUID()]
        );
        $Connection->update(
            $Handler->tableOrderProcess(),
            [
                'c_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'data' => json_encode(['basketConditionOrder' => 2], JSON_THROW_ON_ERROR)
            ],
            ['hash' => $singleUseProcess->getUUID()]
        );

        CleanupOrderInProcess::run(['days' => 14]);

        self::assertNull($this->findRow($Handler->tableOrderProcess(), 'hash', $oldProcess->getUUID()));
        self::assertNull($this->findRow($Handler->tableOrderProcess(), 'hash', $singleUseProcess->getUUID()));
        self::assertNotNull($this->findRow($Handler->tableOrderProcess(), 'hash', $recentProcess->getUUID()));
    }

    public function testMigrationV2KeepsOrderIdentifierColumnsPortableAndRepeatable(): void
    {
        $Console = new MigrationV2();
        EventHandling::onQuiqqerMigrationV2($Console);
        EventHandling::onQuiqqerMigrationV2($Console);
        $SchemaManager = QUI::getSchemaManager();
        $Handler = Handler::getInstance();

        foreach ([$Handler->table(), $Handler->tableOrderProcess()] as $table) {
            $Table = $SchemaManager->introspectTable($table);

            foreach (['invoice_id', 'customerId'] as $columnName) {
                $Column = $Table->getColumn($columnName);
                self::assertInstanceOf(StringType::class, $Column->getType());
                self::assertSame(50, $Column->getLength());
                self::assertFalse($Column->getNotnull());
            }
        }
    }

    public function testPaymentReceiverReadsFinalOrderState(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Config = QUI::getPackage('quiqqer/order')->getConfig();
        self::assertNotNull($Config);
        $Config->set('order', 'autoInvoice', '');
        $Config->save();
        $OrderInProcess = Factory::getInstance()->createOrderInProcess($SystemUser);
        $processHash = $OrderInProcess->getUUID();
        $this->orderProcessHashes[] = $processHash;
        $OrderInProcess->setAttribute('no_invoice_auto_create', true);
        $Order = $OrderInProcess->createOrder($SystemUser);
        $this->orderHashes[] = $Order->getUUID();
        $Receiver = new PaymentReceiver($Order->getUUID());
        $ReceiverByDocumentNo = new PaymentReceiver($Order->getPrefixedNumber());

        self::assertSame('Order', $Receiver::getType());
        self::assertSame($Order->getPrefixedNumber(), $Receiver->getDocumentNo());
        self::assertSame($Order->getPrefixedNumber(), $ReceiverByDocumentNo->getDocumentNo());
        self::assertSame($Order->getCustomer()->getCustomerNo(), $Receiver->getDebtorNo());
        self::assertSame($Order->getCurrency()->getCode(), $Receiver->getCurrency()->getCode());
        self::assertSame((float)$Order->getAttribute('sum'), $Receiver->getAmountTotal());
        self::assertSame((float)$Order->getAttribute('toPay'), $Receiver->getAmountOpen());
        self::assertSame((float)$Order->getAttribute('paid'), $Receiver->getAmountPaid());
        self::assertSame((int)$Order->getAttribute('paid_status'), $Receiver->getPaymentStatus());
        self::assertSame(false, $Receiver->getDueDate());
        self::assertSame(
            $Order->getCustomer()->getStandardAddress()->getUUID(),
            $Receiver->getDebtorAddress()->getUUID()
        );
        self::assertSame(
            substr($Order->getCreateDate(), 0, 10),
            $Receiver->getDate()->format('Y-m-d')
        );
    }

    public function testOrderCanBeCopiedClearedReloadedAndDeleted(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Order = Factory::getInstance()->create($SystemUser);
        $this->orderHashes[] = $Order->getUUID();
        $marker = $this->createMarker('copy-source');
        $Order->setData('phpunit_flow', $marker);
        $Order->addComment('PHPUnit source order ' . $marker);
        $Order->update($SystemUser);

        $Copy = $Order->copy($SystemUser);
        $copyHash = $Copy->getUUID();
        $this->orderHashes[] = $copyHash;

        self::assertNotSame($Order->getUUID(), $copyHash);
        self::assertNull($Copy->getDataEntry('phpunit_flow'));
        self::assertSame([], $Copy->getComments()->toArray());
        self::assertSame($copyHash, $Copy->getGlobalProcessId());

        $copyMarker = $this->createMarker('copy');
        $Copy->setData('phpunit_flow', $copyMarker);
        $Copy->addComment('PHPUnit copied order ' . $copyMarker);
        $Copy->update($SystemUser);
        $Copy->clear($SystemUser);

        $ReloadedCopy = Handler::getInstance()->get($copyHash);
        self::assertNull($ReloadedCopy->getDataEntry('phpunit_flow'));
        self::assertSame([], $ReloadedCopy->getArticles()->getArticles());
        self::assertSame(QUI\ERP\Constants::PAYMENT_STATUS_OPEN, $ReloadedCopy->getAttribute('paid_status'));

        $ReloadedCopy->delete($SystemUser);
        self::assertNull($this->findRow(Handler::getInstance()->table(), 'hash', $copyHash));
    }

    public function testBasketCanTransferItsStateToProcessingOrder(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $OrderInProcess = Factory::getInstance()->createOrderInProcess($SystemUser);
        $processHash = $OrderInProcess->getUUID();
        $this->orderProcessHashes[] = $processHash;
        $Basket = Factory::getInstance()->createBasket($SystemUser);
        $basketId = (int)$Basket->getId();
        $this->basketIds[] = $basketId;

        $OrderInProcess->setData('phpunit_flow', $this->createMarker('before-transfer'));
        $OrderInProcess->update($SystemUser);
        $Basket->toOrder($OrderInProcess);

        $ReloadedProcess = Handler::getInstance()->getOrderInProcessByHash($processHash);
        self::assertSame($processHash, $ReloadedProcess->getUUID());
        self::assertNull($ReloadedProcess->getDataEntry('phpunit_flow'));
        self::assertSame([], $ReloadedProcess->getArticles()->getArticles());
        self::assertSame(0, $Basket->count());

        $persistedProcess = $this->fetchRow(
            Handler::getInstance()->tableOrderProcess(),
            'hash',
            $processHash
        );
        self::assertNull(json_decode($persistedProcess['data'], true));
        self::assertIsArray(json_decode($persistedProcess['articles'], true));
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchRow(string $table, string $field, int|string $value): array
    {
        $row = $this->findRow($table, $field, $value);
        self::assertNotNull($row);

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRow(string $table, string $field, int|string $value): ?array
    {
        $Connection = $this->getConnection();
        $QueryBuilder = $Connection->createQueryBuilder();
        $row = $QueryBuilder
            ->select('*')
            ->from($Connection->quoteIdentifier($table))
            ->where($QueryBuilder->expr()->eq($Connection->quoteIdentifier($field), ':value'))
            ->setParameter('value', $value)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return $row === false ? null : $row;
    }

    private function createMarker(string $type): string
    {
        return 'phpunit-' . $type . '-' . bin2hex(random_bytes(8));
    }

    private function getConnection(): Connection
    {
        return QUI::getDataBaseConnection();
    }

    private function getMaximumId(string $table): int
    {
        $Connection = $this->getConnection();
        $QueryBuilder = $Connection->createQueryBuilder();

        return (int)$QueryBuilder
            ->select('MAX(' . $Connection->quoteIdentifier('id') . ')')
            ->from($Connection->quoteIdentifier($table))
            ->executeQuery()
            ->fetchOne();
    }

    private static function ensureDefaultArea(): void
    {
        if (self::$defaultAreaReady) {
            return;
        }

        try {
            QUI\ERP\Defaults::getArea();
            self::$defaultAreaReady = true;

            return;
        } catch (QUI\Exception) {
        }

        $Config = QUI::getPackage('quiqqer/tax')->getConfig();

        if (!$Config) {
            throw new RuntimeException('The tax configuration is not available for the Order tests.');
        }

        self::$originalDefaultArea = $Config->getValue('shop', 'area');
        $Country = QUI\ERP\Defaults::getCountry();
        $Area = (new AreasHandler())->createChild([
            'countries' => $Country->getCode(),
            'data' => json_encode([
                'importLocale' => 'PHPUnit Order default area'
            ], JSON_THROW_ON_ERROR)
        ]);

        if (!$Area instanceof Area) {
            throw new RuntimeException('The PHPUnit Order default area could not be created.');
        }

        self::$createdAreaId = (int)$Area->getId();
        $Config->set('shop', 'area', (string)self::$createdAreaId);
        $Config->save();
        self::$defaultAreaReady = true;
    }
}
