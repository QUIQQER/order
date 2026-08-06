<?php

declare(strict_types=1);

namespace QUITests\ERP\Order\DemoData;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\DemoData\DTO\DemoDataCreationContext;
use QUI\ERP\DemoData\DTO\DemoDataDateRange;
use QUI\ERP\DemoData\DTO\DemoDataReference;
use QUI\ERP\DemoData\DTO\DemoDataReferenceCollection;
use QUI\ERP\Order\DemoData\OrderDemoDataCreator;
use QUI\ERP\Order\Handler;

class OrderDemoDataCreatorIntegrationTest extends TestCase
{
    /** @var list<string> */
    private array $orderUuids = [];

    protected function tearDown(): void
    {
        $connection = QUI::getDataBaseConnection();

        foreach ($this->orderUuids as $orderUuid) {
            $connection->delete(Handler::getInstance()->table(), ['hash' => $orderUuid]);
        }

        parent::tearDown();
    }

    public function testCreatedDemoOrdersContainAnArticle(): void
    {
        $systemUser = QUI::getUsers()->getSystemUser();
        $creator = new OrderDemoDataCreator();
        $createdDemoData = $creator->createDemoData(new DemoDataCreationContext(
            new DemoDataReferenceCollection([
                'quiqqer.customer' => [
                    new DemoDataReference('quiqqer.customer', 'customer', $systemUser->getUUID())
                ]
            ]),
            [new DemoDataDateRange(
                new DateTimeImmutable('2026-01-01 00:00:00'),
                new DateTimeImmutable('2026-12-31 23:59:59')
            )]
        ));

        self::assertCount(10, $createdDemoData->all());

        foreach ($createdDemoData->all() as $createdDemoDatum) {
            $this->orderUuids[] = $createdDemoDatum->entityUuid;
            $order = Handler::getInstance()->getOrderByHash($createdDemoDatum->entityUuid);

            self::assertCount(1, $order->getArticles()->getArticles());
        }
    }
}
