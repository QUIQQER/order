<?php

namespace QUITests\ERP\Order\ProcessingStatus;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\ProcessingStatus\Status;
use QUI\ERP\User;
use ReflectionClass;
use ReflectionProperty;

class StatusUnitTest extends TestCase
{
    public function testStatusChangeNotificationUsesProvidedLocaleAndOrderData(): void
    {
        $Status = (new ReflectionClass(Status::class))->newInstanceWithoutConstructor();
        $this->setProperty($Status, 'id', 7);

        $Customer = $this->createMock(User::class);
        $Customer->method('getName')->willReturn('Alice Example');

        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getPrefixedNumber')->willReturn('ORD-100');
        $Order->method('getCreateDate')->willReturn('2026-08-13 10:00:00');

        $Locale = $this->createMock(QUI\Locale::class);
        $Locale->method('formatDate')
            ->with('2026-08-13 10:00:00')
            ->willReturn('13.08.2026');
        $Locale->method('get')->willReturnCallback(
            static function (string $package, string $key, array | bool $replacements = false): string {
                self::assertSame('quiqqer/order', $package);

                if ($key === 'processing.status.7') {
                    self::assertFalse($replacements);
                    return 'Ready';
                }

                self::assertSame('processing.status.notification.7', $key);
                self::assertIsArray($replacements);
                self::assertSame([
                    'customerName' => 'Alice Example',
                    'orderNo' => 'ORD-100',
                    'orderDate' => '13.08.2026',
                    'orderStatus' => 'Ready'
                ], $replacements);

                return 'Order status changed';
            }
        );

        self::assertSame(
            'Order status changed',
            $Status->getStatusChangeNotificationText($Order, $Locale)
        );
    }

    public function testGettersAndToArrayWithProvidedLocale(): void
    {
        $Status = (new ReflectionClass(Status::class))->newInstanceWithoutConstructor();
        $this->setProperty($Status, 'id', 7);
        $this->setProperty($Status, 'color', '#123456');
        $this->setProperty($Status, 'notification', true);

        $Locale = QUI::getLocale();

        $this->assertSame(7, $Status->getId());
        $this->assertSame('#123456', $Status->getColor());
        $this->assertTrue($Status->isAutoNotification());
        $this->assertIsString($Status->getTitle($Locale));

        $asArray = $Status->toArray($Locale);

        $this->assertSame(7, $asArray['id']);
        $this->assertSame('#123456', $asArray['color']);
        $this->assertTrue($asArray['notification']);
        $this->assertArrayHasKey('title', $asArray);
        $this->assertArrayHasKey('statusChangeText', $asArray);
        $this->assertSame([], $asArray['statusChangeText']);
    }

    private function setProperty(object $object, string $propertyName, mixed $value): void
    {
        $property = $this->findProperty($object, $propertyName);
        $property->setValue($object, $value);
    }

    private function findProperty(object $object, string $propertyName): ReflectionProperty
    {
        $reflection = new ReflectionClass($object);

        while ($reflection) {
            if ($reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);
                $property->setAccessible(true);

                return $property;
            }

            $reflection = $reflection->getParentClass();
        }

        $this->fail('Property not found: ' . $propertyName);
    }
}
