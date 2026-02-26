<?php

namespace QUITests\ERP\Order\Utils;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\Controls\AbstractOrderingStep;
use QUI\ERP\Order\Utils\OrderProcessSteps;
use ReflectionClass;

class OrderProcessStepsUnitTest extends TestCase
{
    public function testAppendAcceptsOnlyOrderingStepInstances(): void
    {
        $Steps = new OrderProcessSteps();
        $Step = $this->createStepWithoutConstructor();

        $Steps->append($Step);
        $Steps->append(new \stdClass());

        $this->assertCount(1, $Steps->toArray());
        $this->assertSame($Step, $Steps->toArray()[0]);
    }

    public function testGetInstanceFiltersChildrenByAllowedType(): void
    {
        $Step = $this->createStepWithoutConstructor();

        $Steps = OrderProcessSteps::getInstance([
            'children' => [
                $Step,
                new \stdClass()
            ]
        ]);

        $this->assertCount(1, $Steps->toArray());
        $this->assertSame($Step, $Steps->first());
    }

    private function createStepWithoutConstructor(): AbstractOrderingStep
    {
        $Step = new class extends AbstractOrderingStep {
            public function getName(null | \QUI\Locale $Locale = null): string
            {
                return 'test';
            }

            public function validate(): void
            {
            }

            public function save(): void
            {
            }
        };

        return (new ReflectionClass($Step::class))->newInstanceWithoutConstructor();
    }
}
