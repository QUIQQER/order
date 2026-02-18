<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\Settings;
use ReflectionClass;
use ReflectionProperty;

class SettingsUnitTest extends TestCase
{
    public function testGetAndSetSettingsValues(): void
    {
        $Settings = $this->createSettingsWithoutConstructor();
        $this->setProperty($Settings, 'settings', []);

        $this->assertFalse($Settings->get('order', 'autoInvoice'));

        $Settings->set('order', 'autoInvoice', 'onPaid');
        $this->assertSame('onPaid', $Settings->get('order', 'autoInvoice'));
    }

    public function testInvoiceFlagsAndForceToggleWhenInvoiceNotInstalled(): void
    {
        $Settings = $this->createSettingsWithoutConstructor();
        $this->setProperty($Settings, 'isInvoiceInstalled', false);
        $this->setProperty($Settings, 'forceCreateInvoice', true);

        $this->assertFalse($Settings->isInvoiceInstalled());
        $this->assertFalse($Settings->createInvoiceOnOrder());
        $this->assertFalse($Settings->createInvoiceOnPaid());
        $this->assertFalse($Settings->createInvoiceByPayment());
        $this->assertFalse($Settings->forceCreateInvoice());
    }

    public function testForceCreateInvoiceToggleWhenInvoiceInstalled(): void
    {
        $Settings = $this->createSettingsWithoutConstructor();
        $this->setProperty($Settings, 'isInvoiceInstalled', true);
        $this->setProperty($Settings, 'forceCreateInvoice', false);

        $this->assertTrue($Settings->isInvoiceInstalled());
        $this->assertFalse($Settings->forceCreateInvoice());

        $Settings->forceCreateInvoiceOn();
        $this->assertTrue($Settings->forceCreateInvoice());

        $Settings->forceCreateInvoiceOff();
        $this->assertFalse($Settings->forceCreateInvoice());
    }

    private function createSettingsWithoutConstructor(): Settings
    {
        return (new ReflectionClass(Settings::class))->newInstanceWithoutConstructor();
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
