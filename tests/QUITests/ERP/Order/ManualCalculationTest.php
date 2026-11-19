<?php

namespace QUITests\ERP\Order;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Enums\Accounting\CalculationMode;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\User;
use ReflectionProperty;

class ManualCalculationTest extends TestCase
{
    public function testImportedManualSnapshotSurvivesRecalculationAndSqliteReload(): void
    {
        $Order = $this->order();
        $snapshot = $this->snapshot();
        $Order->importArticles($snapshot);
        $Order->recalculate();
        $Articles = $this->articles($Order);
        $this->assertSnapshot($snapshot, $Articles);

        $Connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $Connection->executeStatement('CREATE TABLE snapshots (id INTEGER PRIMARY KEY, articles TEXT)');
        $Connection->insert('snapshots', ['id' => 1, 'articles' => $Articles->toJSON()]);
        $Reloaded = $this->order();
        $Reloaded->importArticles(json_decode($Connection->fetchOne('SELECT articles FROM snapshots'), true));
        $Reloaded->recalculate();
        $this->assertSnapshot($snapshot, $this->articles($Reloaded));
        $Connection->close();
    }

    public function testAutomaticWrapperAndLegacyRowsKeepHistoricalPositionCaches(): void
    {
        foreach ([true, false] as $wrapped) {
            $Order = $this->order();
            $snapshot = $this->snapshot();
            $snapshot['calculationMode'] = 'automatic';
            $snapshot['articles'][0]['quantity'] = 2;
            $Order->importArticles($wrapped ? $snapshot : $snapshot['articles']);
            $Articles = $this->articles($Order);
            $Articles->recalculate();
            self::assertSame(CalculationMode::AUTOMATIC, $Articles->getCalculationMode());
            self::assertSame(8.395, $Articles->toArray()['articles'][0]['calculated']['nettoSum']);
            self::assertSame(8.395, $Articles->toArray()['articles'][0]['calculated']['sum']);
        }
    }

    private function order(): OrderInProcess
    {
        $Order = $this->getMockBuilder(OrderInProcess::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCurrency', 'getCustomer', 'getOrderId', 'isSuccessful'])
            ->getMock();
        $Order->method('getCurrency')->willReturn(QUI\ERP\Defaults::getCurrency());
        $Order->method('getCustomer')->willReturn($this->createMock(User::class));
        $Order->expects(self::never())->method('getOrderId');
        $Order->expects(self::never())->method('isSuccessful');
        (new ReflectionProperty(AbstractOrder::class, 'Articles'))->setValue($Order, new ArticleList());
        return $Order;
    }

    private function articles(OrderInProcess $Order): ArticleList
    {
        return (new ReflectionProperty(AbstractOrder::class, 'Articles'))->getValue($Order);
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/ManualArticleList.json'), true);
    }

    /** @param array<string, mixed> $snapshot */
    private function assertSnapshot(array $snapshot, ArticleList $Articles): void
    {
        self::assertSame(CalculationMode::MANUAL, $Articles->getCalculationMode());
        foreach ($snapshot['calculations'] as $key => $value) {
            self::assertSame($value, $Articles->getCalculations()[$key], $key);
        }
        self::assertSame(8.395, $Articles->toArray()['articles'][0]['calculated']['nettoSum']);
        self::assertSame(1.5951, $Articles->toArray()['articles'][0]['calculated']['vatArray']['sum']);
    }
}
