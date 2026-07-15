<?php

namespace QUITests\ERP\Order;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Order\Basket\Basket;
use QUI\ERP\Order\Factory;
use QUI\ERP\Order\Handler;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderInProcess;
use ReflectionProperty;

class OrderLifecycleDatabaseTest extends TestCase
{
    /** @var list<string> */
    private array $orderHashes = [];

    /** @var list<string> */
    private array $orderProcessHashes = [];

    /** @var list<int> */
    private array $basketIds = [];

    private mixed $originalOrderIndex;
    private mixed $originalSessionUser;

    protected function setUp(): void
    {
        parent::setUp();

        $Config = QUI::getPackage('quiqqer/order')->getConfig();
        self::assertNotNull($Config);
        $this->originalOrderIndex = $Config->getValue('order', 'orderCurrentIdIndex');
        $Users = QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $this->originalSessionUser = $Session->getValue($Users);
        $Session->setValue($Users, $Users->getSystemUser());
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
}
