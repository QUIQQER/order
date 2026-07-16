<?php

namespace QUITests\ERP\Order;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\StringType;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Order\Basket\Basket;
use QUI\ERP\Order\EventHandling;
use QUI\ERP\Order\Factory;
use QUI\ERP\Order\Handler;
use Throwable;

use function getenv;
use function uniqid;

class HandlerBasketIntegrationTest extends TestCase
{
    private ?int $basketId = null;

    protected function setUp(): void
    {
        if (!defined('SYSTEM_INTERN')) {
            define('SYSTEM_INTERN', true);
        }

        if ($this->isCiEnvironment()) {
            self::markTestSkipped('DB integration tests are skipped in CI.');
        }

        try {
            $this->getConnection()->fetchOne('SELECT 1');
        } catch (Throwable $Exception) {
            self::markTestSkipped(
                'DB integration test skipped because no database connection is available: ' . $Exception->getMessage()
            );
        }
    }

    protected function tearDown(): void
    {
        if ($this->basketId === null) {
            return;
        }

        $this->getConnection()->delete(
            Handler::getInstance()->tableBasket(),
            ['id' => $this->basketId]
        );

        $this->basketId = null;
    }

    public function testBasketCanBeLoadedByIdHashAndDataLookup(): void
    {
        $User = QUI::getUsers()->getSystemUser();

        Factory::getInstance()->createBasket($User);

        $createdBasket = $this->fetchLatestBasketFromDatabase((string)$User->getUUID());
        $this->basketId = (int)$createdBasket['id'];
        $hash = uniqid('phpunit-basket-', true);

        $Basket = new Basket($this->basketId, $User);
        $Basket->setHash($hash);
        $Basket->save();

        $Handler = Handler::getInstance();

        $BasketById = $Handler->getBasketById($this->basketId, $User);
        $BasketByHash = $Handler->getBasketByHash($hash, $User);
        $basketData = $Handler->getBasketData($this->basketId, $User);
        $databaseBasket = $this->fetchBasketFromDatabase($this->basketId);

        $this->assertInstanceOf(Basket::class, $BasketById);
        $this->assertSame($this->basketId, (int)$BasketById->getId());

        $this->assertInstanceOf(Basket::class, $BasketByHash);
        $this->assertSame($this->basketId, (int)$BasketByHash->getId());

        $this->assertSame($this->basketId, (int)$basketData['id']);
        $this->assertSame((string)$User->getUUID(), (string)$basketData['uid']);
        $this->assertSame($hash, $basketData['hash']);

        $this->assertSame($this->basketId, (int)$databaseBasket['id']);
        $this->assertSame((string)$User->getUUID(), (string)$databaseBasket['uid']);
        $this->assertSame($hash, $databaseBasket['hash']);
    }

    public function testLegacyBasketUserIdIsMigratedToUuid(): void
    {
        $LegacyUser = null;

        foreach (QUI::getUsers()->getUserIds() as $userData) {
            $userId = $userData['id'] ?? null;
            $userUuid = $userData['uuid'] ?? null;

            if (!is_numeric($userId) || !is_string($userUuid) || $userUuid === '' || is_numeric($userUuid)) {
                continue;
            }

            $LegacyUser = QUI::getUsers()->get((string)$userId);
            break;
        }

        if ($LegacyUser === null || $LegacyUser->getId() === false) {
            $this->markTestSkipped('No user with a legacy ID and UUID is available.');
        }

        $Connection = $this->getConnection();
        $Connection->insert(
            Handler::getInstance()->tableBasket(),
            ['uid' => (string)$LegacyUser->getId()]
        );
        $this->basketId = (int)$Connection->lastInsertId();

        EventHandling::migrateBasketUserIds();

        $basket = $this->fetchBasketFromDatabase($this->basketId);
        $this->assertSame($LegacyUser->getUUID(), $basket['uid']);

        $UidColumn = QUI::getSchemaManager()
            ->introspectTable(Handler::getInstance()->tableBasket())
            ->getColumn('uid');

        $this->assertInstanceOf(StringType::class, $UidColumn->getType());
        $this->assertSame(50, $UidColumn->getLength());
        $this->assertTrue($UidColumn->getNotnull());
    }

    private function fetchLatestBasketFromDatabase(string $userId): array
    {
        $basket = $this->getConnection()->fetchAssociative(
            'SELECT id, uid, hash FROM ' . Handler::getInstance()->tableBasket() . ' WHERE uid = ? ORDER BY id DESC',
            [$userId]
        );

        $this->assertIsArray($basket);

        return $basket;
    }

    private function fetchBasketFromDatabase(int $basketId): array
    {
        $basket = $this->getConnection()->fetchAssociative(
            'SELECT id, uid, hash FROM ' . Handler::getInstance()->tableBasket() . ' WHERE id = ?',
            [$basketId]
        );

        $this->assertIsArray($basket);

        return $basket;
    }

    private function getConnection(): Connection
    {
        return QUI::getDataBaseConnection();
    }

    private function isCiEnvironment(): bool
    {
        return getenv('CI')
            || getenv('GITHUB_ACTIONS')
            || getenv('GITLAB_CI')
            || getenv('JENKINS_URL')
            || getenv('BUILDKITE')
            || getenv('TEAMCITY_VERSION');
    }
}
