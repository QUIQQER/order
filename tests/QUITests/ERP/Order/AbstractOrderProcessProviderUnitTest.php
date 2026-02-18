<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\AbstractOrderProcessProvider;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderProcess;
use QUI\ERP\Order\Utils\OrderProcessSteps;
use ReflectionClass;
use ReflectionProperty;

class AbstractOrderProcessProviderUnitTest extends TestCase
{
    public function testDefaultLifecycleMethodsSetFinishState(): void
    {
        $Provider = new class extends AbstractOrderProcessProvider {
        };

        $Order = $this->createOrderWithoutConstructor();

        $this->assertSame(
            AbstractOrderProcessProvider::PROCESSING_STATUS_FINISH,
            $Provider->onOrderStart($Order)
        );

        $this->assertSame(
            AbstractOrderProcessProvider::PROCESSING_STATUS_FINISH,
            $Provider->onOrderSuccess($Order)
        );

        $this->assertSame(
            AbstractOrderProcessProvider::PROCESSING_STATUS_FINISH,
            $Provider->onOrderAbort($Order)
        );

        $this->assertSame(
            AbstractOrderProcessProvider::PROCESSING_STATUS_FINISH,
            $this->getProperty($Provider, 'currentStatus')
        );
    }

    public function testDefaultDisplayAndErrorsState(): void
    {
        $Provider = new class extends AbstractOrderProcessProvider {
        };

        $Order = $this->createOrderWithoutConstructor();
        $this->assertSame('', $Provider->getDisplay($Order));
        $this->assertFalse($Provider->hasErrors());

        $this->setProperty($Provider, 'hasErrors', true);
        $this->assertTrue($Provider->hasErrors());
    }

    public function testInitStepsDefaultImplementationIsNoop(): void
    {
        $Provider = new class extends AbstractOrderProcessProvider {
        };

        $Steps = new OrderProcessSteps();
        $Process = (new ReflectionClass(OrderProcess::class))->newInstanceWithoutConstructor();

        $Provider->initSteps($Steps, $Process);
        $this->assertTrue(true);
    }

    private function createOrderWithoutConstructor(): Order
    {
        return (new ReflectionClass(Order::class))->newInstanceWithoutConstructor();
    }

    private function setProperty(object $object, string $propertyName, mixed $value): void
    {
        $property = $this->findProperty($object, $propertyName);
        $property->setValue($object, $value);
    }

    private function getProperty(object $object, string $propertyName): mixed
    {
        $property = $this->findProperty($object, $propertyName);
        return $property->getValue($object);
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
