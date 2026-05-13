<?php

namespace QUITests\ERP\Order\ProcessingStatus;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Order\ProcessingStatus\Status;
use ReflectionClass;
use ReflectionProperty;

class StatusUnitTest extends TestCase
{
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
