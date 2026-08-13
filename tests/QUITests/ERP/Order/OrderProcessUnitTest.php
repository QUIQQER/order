<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Controls\AbstractOrderingStep;
use QUI\ERP\Order\Controls\OrderProcess\Checkout;
use QUI\ERP\Order\Controls\OrderProcess\CustomerData;
use QUI\ERP\Order\Controls\OrderProcess\Finish;
use QUI\ERP\Order\Controls\OrderProcess\Processing;
use QUI\ERP\Order\OrderProcess;
use QUI\ERP\Order\Utils\OrderProcessSteps;
use QUITests\ERP\Order\Fixtures\TestableOrderProcess;
use QUITests\ERP\Order\Fixtures\TestOrderProcessMessageHandler;
use ReflectionClass;
use ReflectionProperty;

class OrderProcessUnitTest extends TestCase
{
    public function testStepNavigationUsesConfiguredOrderAndSteps(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('isSuccessful')->willReturn(0);
        $Order->method('getUUID')->willReturn('order-uuid');

        $Basket = $this->createStep('Basket');
        $CustomerData = $this->createStep('CustomerData', CustomerData::class);
        $Checkout = $this->createStep('Checkout', Checkout::class);
        $Finish = $this->createStep('Finish', Finish::class);
        $Processing = $this->createStep('Processing', Processing::class);

        $Process = $this->createProcess(
            $Order,
            [$Basket, $CustomerData, $Checkout, $Finish],
            $Processing
        );
        $Process->setAttribute('step', 'CustomerData');

        self::assertSame($CustomerData, $Process->getCurrentStep());
        self::assertSame($Basket, $Process->getFirstStep());
        self::assertSame($Finish, $Process->getLastStep());
        self::assertSame($Checkout, $Process->getNextStep());
        self::assertSame($Basket, $Process->getPreviousStep());
        self::assertSame('Checkout', $Process->invokeGetNextStepName());
        self::assertSame('Basket', $Process->invokeGetPreviousStepName());
        self::assertSame($Checkout, $Process->invokeGetStepByName('Checkout'));
        self::assertFalse($Process->invokeGetStepByName('Unknown'));
        self::assertFalse($Process->getNextStep($Finish));

        $Process->setAttribute('step', 'Unknown');
        self::assertSame('Basket', $Process->invokeGetCurrentStepName());
        self::assertSame($Basket, $Process->getCurrentStep());

        $Process->setAttribute('step', 'Processing');
        self::assertSame($Processing, $Process->getCurrentStep());
        self::assertSame($Processing, $Process->getNextStep());
        self::assertSame('order-uuid', $Process->getAttribute('orderHash'));
    }

    public function testSuccessfulOrderReturnsFinishStepAndCleansUp(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('isSuccessful')->willReturn(1);
        $Order->method('getUUID')->willReturn('successful-uuid');

        $Basket = $this->createStep('Basket');
        $Processing = $this->createStep('Processing', Processing::class);
        $Process = $this->createProcess($Order, [$Basket], $Processing);
        $Process->setAttribute('step', 'Basket');

        $Next = $Process->getNextStep();

        self::assertInstanceOf(Finish::class, $Next);
        self::assertSame('successful-uuid', $Process->getAttribute('orderHash'));
        self::assertSame(1, $Process->getCleanupCalls());
    }

    public function testProcessingPreviousStepResetsTermsAndReturnsStepBeforeCheckout(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getUUID')->willReturn('terms-uuid');

        $Basket = $this->createStep('Basket');
        $CustomerData = $this->createStep('CustomerData', CustomerData::class);
        $Checkout = $this->createStep('Checkout', Checkout::class);
        $Finish = $this->createStep('Finish', Finish::class);
        $Processing = $this->createStep('Processing', Processing::class);
        $Process = $this->createProcess(
            $Order,
            [$Basket, $CustomerData, $Checkout, $Finish],
            $Processing
        );
        $Process->setAttribute('step', 'Processing');

        $Session = QUI::getSession();
        $key = 'termsAndConditions-terms-uuid';
        $original = $Session->get($key);

        try {
            $Session->set($key, 1);

            self::assertSame($CustomerData, $Process->getPreviousStep());
            self::assertSame(0, $Session->get($key));
        } finally {
            $Session->set($key, $original);
        }
    }

    public function testStepUrlsAndHashesOnlyIncludeConfiguredOrderHash(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getUUID')->willReturn('hash-123');

        $Process = new TestableOrderProcess();
        $Process->setTestOrder($Order);
        $Process->setTestUrl('/checkout');

        self::assertSame('/checkout/CustomerData', $Process->getStepUrl('CustomerData'));
        self::assertSame('', $Process->getStepHash());

        $Process->setAttribute('orderHash', true);

        self::assertSame('/checkout/CustomerData/hash-123', $Process->getStepUrl('CustomerData'));
        self::assertSame('hash-123', $Process->getStepHash());
    }

    public function testStepsCanBeSortedAndMappedToProcessAndOrder(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Late = $this->createStep('Late', priority: 20);
        $Early = $this->createStep('Early', priority: 10);
        $Processing = $this->createStep('Processing', Processing::class);
        $Process = $this->createProcess($Order, [$Late, $Early], $Processing);
        $Steps = new OrderProcessSteps([$Late, $Early]);

        $Process->invokeSortSteps($Steps);

        self::assertSame([$Early, $Late], $Steps->toArray());
        self::assertSame(
            ['Early' => $Early, 'Late' => $Late],
            $Process->invokeParseStepsToArray($Steps)
        );
        self::assertSame($Process, $Early->getAttribute('Process'));
        self::assertSame($Order, $Early->getAttribute('Order'));
    }

    public function testSubmissionSavesKnownCurrentStep(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Current = $this->createStep('CustomerData', CustomerData::class);
        $Current->expects(self::once())->method('save');
        $Processing = $this->createStep('Processing', Processing::class);
        $Process = $this->createProcess($Order, [$Current], $Processing);
        $request = $_REQUEST;

        try {
            $_REQUEST['current'] = 'Unknown';
            $Process->invokeCheckSubmission();

            $_REQUEST['current'] = 'CustomerData';
            $Process->invokeCheckSubmission();
        } finally {
            $_REQUEST = $request;
        }
    }

    public function testStepMessagesAreValidatedReturnedOnceAndCleared(): void
    {
        $Session = QUI::getSession();
        $original = $Session->get(OrderProcess::MESSAGES_SESSION_KEY);

        try {
            $Session->set(OrderProcess::MESSAGES_SESSION_KEY, null);
            $Process = new TestableOrderProcess();
            $step = CustomerData::class;

            $Process->addStepMessage(1, TestOrderProcessMessageHandler::class, 'invalid-step');
            self::assertEmpty($Session->get(OrderProcess::MESSAGES_SESSION_KEY));

            $Process->addStepMessage(7, TestOrderProcessMessageHandler::class, $step);
            $messages = $Process->invokeGetStepMessages($step);

            self::assertCount(1, $messages);
            self::assertSame('message-7', $messages[0]->getMsg());
            self::assertSame([], $Process->invokeGetStepMessages($step));

            $Process->clearStepMessages();
            self::assertFalse($Session->get(OrderProcess::MESSAGES_SESSION_KEY));
        } finally {
            $Session->set(OrderProcess::MESSAGES_SESSION_KEY, $original);
        }
    }

    public function testCheckProcessingStopsWithoutOrderPaymentTermsOrFinishStep(): void
    {
        $Current = $this->createStep('CustomerData', CustomerData::class);
        $Processing = $this->createStep('Processing', Processing::class);
        $Process = $this->createProcess(null, [$Current], $Processing);
        $Process->setAttribute('step', 'CustomerData');

        self::assertFalse($Process->invokeCheckProcessing());

        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getPayment')->willReturn(null);
        $Process->setTestOrder($Order);
        self::assertFalse($Process->invokeCheckProcessing());

        $Payment = $this->createMock(Payment::class);
        $OrderWithPayment = $this->createMock(AbstractOrder::class);
        $OrderWithPayment->method('getPayment')->willReturn($Payment);
        $OrderWithPayment->method('getUUID')->willReturn('payment-uuid');
        $OrderWithPayment->method('isSuccessful')->willReturn(0);
        $Process->setTestOrder($OrderWithPayment);

        $Session = QUI::getSession();
        $key = 'termsAndConditions-payment-uuid';
        $original = $Session->get($key);

        try {
            $Session->set($key, 0);
            self::assertFalse($Process->invokeCheckProcessing());

            $Session->set($key, 1);
            self::assertFalse($Process->invokeCheckProcessing());
        } finally {
            $Session->set($key, $original);
        }
    }

    public function testPresetOrderStepsAndSiteAvoidGlobalResolution(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Site = $this->createMock(QUI\Interfaces\Projects\Site::class);
        $Step = $this->createStep('Basket');
        $Process = (new ReflectionClass(OrderProcess::class))->newInstanceWithoutConstructor();

        $this->setProperty($Process, 'Order', $Order);
        $this->setProperty($Process, 'steps', ['Basket' => $Step]);
        $Process->setAttribute('Site', $Site);

        self::assertSame($Order, $Process->getOrder());
        self::assertSame(['Basket' => $Step], $Process->getSteps());
        self::assertSame($Site, $Process->getSite());
    }

    /**
     * @param list<AbstractOrderingStep> $steps
     */
    private function createProcess(
        ?AbstractOrder $Order,
        array $steps,
        AbstractOrderingStep $Processing
    ): TestableOrderProcess {
        $Process = new TestableOrderProcess();
        $mappedSteps = [];

        foreach ($steps as $Step) {
            $mappedSteps[$Step->getName()] = $Step;
        }

        $Process->setTestOrder($Order);
        $Process->setTestSteps($mappedSteps);
        $Process->setTestProcessingStep($Processing);

        return $Process;
    }

    private function createStep(
        string $name,
        string $type = AbstractOrderingStep::class,
        int $priority = 10
    ): AbstractOrderingStep & MockObject {
        $Step = $this->getMockBuilder(AbstractOrderingStep::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getName', 'validate', 'save', 'getType'])
            ->getMock();
        $Step->method('getName')->willReturn($name);
        $Step->method('getType')->willReturn($type);
        $Step->setAttribute('priority', $priority);

        return $Step;
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $Property = new ReflectionProperty($object, $name);
        $Property->setValue($object, $value);
    }
}
