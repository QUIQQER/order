<?php

namespace QUITests\ERP\Order\ProcessingStatus;

use PHPUnit\Framework\TestCase;
use QUI\Config;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\ProcessingStatus\Handler;
use QUI\ERP\Order\ProcessingStatus\StatusUnknown;
use QUI\ERP\Order\ProcessingStatus\Status;
use QUI\ERP\Order\ProcessingStatus\Factory;
use QUI\Utils\Singleton;
use QUI\ERP\User;
use ReflectionClass;
use ReflectionProperty;

class HandlerUnitTest extends TestCase
{
    public function testListIsLoadedOnceAndRefreshForcesReload(): void
    {
        $Config = $this->createMock(Config::class);
        $Config->expects(self::exactly(2))
            ->method('getSection')
            ->with('processing_status')
            ->willReturnOnConsecutiveCalls([1 => '#111111'], [2 => '#222222']);
        $Handler = $this->createHandler($Config);

        self::assertSame([1 => '#111111'], $Handler->getList());
        self::assertSame([1 => '#111111'], $Handler->getList());
        self::assertSame([2 => '#222222'], $Handler->refreshList());
    }

    public function testInvalidConfiguredListIsNormalizedToEmptyArray(): void
    {
        $Config = $this->createMock(Config::class);
        $Config->method('getSection')->willReturn(false);

        self::assertSame([], $this->createHandler($Config)->getList());
    }

    public function testUnknownStatusAndEmptyCancelledStatusUseStatusUnknown(): void
    {
        $Config = $this->createMock(Config::class);
        $Config->method('get')->with('orderStatus', 'cancelled')->willReturn(false);
        $Handler = $this->createHandler($Config);

        self::assertInstanceOf(StatusUnknown::class, $Handler->getProcessingStatus(0));
        self::assertInstanceOf(StatusUnknown::class, $Handler->getCancelledStatus());
    }

    public function testStatusListCreatesUnknownStatusForIdZero(): void
    {
        $Config = $this->createMock(Config::class);
        $Handler = $this->createHandler($Config);
        $this->setProperty($Handler, 'list', [0 => '#000000']);

        $statuses = $Handler->getProcessingStatusList();

        self::assertCount(1, $statuses);
        self::assertInstanceOf(StatusUnknown::class, $statuses[0]);
    }

    public function testNotificationSettingIsPersistedForUnknownStatus(): void
    {
        $Config = $this->createMock(Config::class);
        $Config->expects(self::once())
            ->method('setValue')
            ->with('processing_status_notification', '0', '1');
        $Config->expects(self::once())->method('save');

        $this->createHandler($Config)->setProcessingStatusNotification(0, true);
    }

    public function testNotificationWithoutCustomerEmailStopsBeforeMailCreation(): void
    {
        $Customer = $this->createMock(User::class);
        $Customer->method('getAttribute')->with('email')->willReturn('');
        $Customer->method('getUUID')->willReturn('customer-uuid');

        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getPrefixedNumber')->willReturn('ORD-100');
        $Order->expects(self::never())->method('addStatusMail');

        $this->createHandler($this->createMock(Config::class))
            ->sendStatusChangeNotification($Order, 1);

        self::assertTrue(true);
    }

    public function testConfiguredStatusesAndCancelledStatusAreCreated(): void
    {
        $Config = $this->createMock(Config::class);
        $Config->method('getSection')->with('processing_status')->willReturn([
            3 => '#333333',
            5 => '#555555'
        ]);
        $Config->method('get')->with('orderStatus', 'cancelled')->willReturn(5);
        $Handler = $this->createHandler($Config);

        $this->withSingletonHandler($Handler, static function () use ($Handler): void {
            self::assertInstanceOf(Status::class, $Handler->getProcessingStatus(3));
            self::assertSame(5, $Handler->getCancelledStatus()->getId());
            self::assertCount(2, $Handler->getProcessingStatusList());
        });
    }

    public function testProcessingStatusFactoryCalculatesNextIds(): void
    {
        $EmptyHandler = $this->createHandler($this->createMock(Config::class));
        $this->setProperty($EmptyHandler, 'list', []);
        $this->withSingletonHandler($EmptyHandler, static function (): void {
            self::assertSame(1, Factory::getInstance()->getNextId());
        });

        $ConfiguredHandler = $this->createHandler($this->createMock(Config::class));
        $this->setProperty($ConfiguredHandler, 'list', [2 => '#2', 9 => '#9']);
        $this->withSingletonHandler($ConfiguredHandler, static function (): void {
            self::assertSame(10, Factory::getInstance()->getNextId());
        });
    }

    public function testProcessingStatusFactoryRejectsDuplicateIdBeforeWritingConfiguration(): void
    {
        $Handler = $this->createHandler($this->createMock(Config::class));
        $this->setProperty($Handler, 'list', [7 => '#777777']);

        $this->withSingletonHandler($Handler, static function (): void {
            try {
                Factory::getInstance()->createProcessingStatus(7, '#000000', []);
                self::fail('A duplicate processing status ID must be rejected.');
            } catch (\QUI\ERP\Order\ProcessingStatus\Exception) {
                self::assertTrue(true);
            }
        });
    }

    private function createHandler(Config $Config): Handler
    {
        $Handler = (new ReflectionClass(Handler::class))->newInstanceWithoutConstructor();
        $this->setProperty($Handler, 'OrderConfig', $Config);
        $this->setProperty($Handler, 'list', null);

        return $Handler;
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $Property = new ReflectionProperty($object, $name);
        $Property->setValue($object, $value);
    }

    private function withSingletonHandler(Handler $Handler, callable $callback): void
    {
        $Instances = new ReflectionProperty(Singleton::class, 'instances');
        $original = $Instances->getValue();
        $instances = $original;
        $instances[Handler::class] = $Handler;
        unset($instances[Factory::class]);
        $Instances->setValue(null, $instances);

        try {
            $callback();
        } finally {
            $Instances->setValue(null, $original);
        }
    }
}
