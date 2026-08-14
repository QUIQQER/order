<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Order\AbstractOrder;
use QUITests\ERP\Order\Fixtures\TestOrderingStep;

class AbstractOrderingStepUnitTest extends TestCase
{
    public function testDefaultStepBehavior(): void
    {
        $Step = new TestOrderingStep();

        self::assertSame('Test', $Step->getName());
        self::assertSame('fa fa-shopping-bag', $Step->getIcon());
        self::assertFalse($Step->hasOwnForm());
        self::assertTrue($Step->showNext());
        self::assertNull($Step->getOrder());
        self::assertTrue($Step->isValid());
        self::assertNull($Step->onExecutePayableStatus());
    }

    public function testTitleUsesConcreteStepClassName(): void
    {
        $Locale = $this->createMock(QUI\Locale::class);
        $Locale->expects(self::once())
            ->method('get')
            ->with('quiqqer/order', 'ordering.step.title.TestOrderingStep')
            ->willReturn('Test step');

        self::assertSame('Test step', (new TestOrderingStep())->getTitle($Locale));
    }

    public function testOrderAttributeIsReturnedOnlyForOrders(): void
    {
        $Step = new TestOrderingStep();
        $Order = $this->createMock(AbstractOrder::class);

        $Step->setAttribute('Order', new \stdClass());
        self::assertNull($Step->getOrder());

        $Step->setAttribute('Order', $Order);
        self::assertSame($Order, $Step->getOrder());
    }

    public function testDomainValidationExceptionMarksStepInvalid(): void
    {
        $Step = new TestOrderingStep();
        $Step->failValidation = true;

        self::assertFalse($Step->isValid());
    }
}
