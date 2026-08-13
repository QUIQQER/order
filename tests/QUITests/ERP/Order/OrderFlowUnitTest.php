<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\AbstractOrderProcessProvider;
use QUI\ERP\Order\Controls\AbstractOrderingStep;
use QUI\ERP\Order\Controls\OrderProcess\CustomerData;
use QUI\ERP\Order\Controls\OrderProcess\Processing;
use QUI\ERP\Order\Handler;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderInProcess;
use QUI\Interfaces\Users\User;
use QUI\Utils\Singleton;
use QUITests\ERP\Order\Fixtures\TestableHandler;
use QUITests\ERP\Order\Fixtures\TestableOrderProcess;
use ReflectionClass;
use ReflectionProperty;

class OrderFlowUnitTest extends TestCase
{
    /** @var array<array-key, mixed> */
    private array $singletonInstances;

    protected function setUp(): void
    {
        parent::setUp();

        $this->singletonInstances = $this->getSingletonInstances();
    }

    protected function tearDown(): void
    {
        $this->setSingletonInstances($this->singletonInstances);

        parent::tearDown();
    }

    public function testOrderProcessCreatesFinalOrderAndDeletesOrderInProcess(): void
    {
        $FinalOrder = $this->createMock(Order::class);
        $FinalOrder->method('getUUID')->willReturn('final-order-uuid');

        $OrderInProcess = $this->createMock(OrderInProcess::class);
        $OrderInProcess->expects(self::once())
            ->method('createOrder')
            ->willReturn($FinalOrder);
        $OrderInProcess->expects(self::once())->method('delete');

        $Provider = $this->createMock(AbstractOrderProcessProvider::class);
        $Provider->expects(self::once())
            ->method('onOrderStart')
            ->with($OrderInProcess)
            ->willReturn(AbstractOrderProcessProvider::PROCESSING_STATUS_FINISH);
        $Provider->expects(self::once())
            ->method('onOrderSuccess')
            ->with($OrderInProcess)
            ->willReturn(AbstractOrderProcessProvider::PROCESSING_STATUS_FINISH);
        $Provider->expects(self::never())->method('onOrderAbort');

        $Handler = new TestableHandler();
        $Handler->setOrderProcessProviders([$Provider]);
        $this->setHandler($Handler);

        $CustomerData = $this->createStep('CustomerData', CustomerData::class);
        $CustomerData->expects(self::once())->method('validate');
        $Process = new TestableOrderProcess();
        $Process->setTestOrder($OrderInProcess);
        $Process->setTestSteps(['CustomerData' => $CustomerData]);
        $Process->setTestProcessingStep($this->createStep('Processing', Processing::class));

        $Process->invokeSend();

        self::assertSame($FinalOrder, $Process->getOrder());
        self::assertSame('final-order-uuid', $Process->getAttribute('orderHash'));
        self::assertSame('Finish', $Process->getAttribute('current'));
        self::assertSame('Finish', $Process->getAttribute('step'));
        self::assertSame(1, $Process->getCleanupCalls());
    }

    public function testLinkedOrderInProcessDelegatesLifecycleToFinalOrder(): void
    {
        $PermissionUser = $this->createMock(User::class);
        $PermissionUser->method('getUUID')->willReturn('creator-uuid');

        $FinalOrder = $this->createMock(Order::class);
        $FinalOrder->method('getUUID')->willReturn('final-order-uuid');
        $FinalOrder->method('isPosted')->willReturn(true);
        $FinalOrder->method('hasInvoice')->willReturn(true);
        $FinalOrder->expects(self::exactly(2))->method('update')->with($PermissionUser);
        $FinalOrder->expects(self::once())->method('clear')->with($PermissionUser);
        $FinalOrder->expects(self::once())->method('setSuccessfulStatus');
        $FinalOrder->expects(self::once())->method('setPaymentStatus')->with(2);

        $Handler = new TestableHandler();
        $Handler->setResolvedOrder($FinalOrder);
        $this->setHandler($Handler);

        $OrderInProcess = (new ReflectionClass(OrderInProcess::class))->newInstanceWithoutConstructor();
        $this->setProperty($OrderInProcess, 'orderId', 'final-order-uuid');
        $this->setProperty($OrderInProcess, 'hash', 'process-uuid');
        $this->setProperty($OrderInProcess, 'cUser', 'creator-uuid');

        self::assertSame('final-order-uuid', $OrderInProcess->getOrderId());
        self::assertSame('final-order-uuid', $OrderInProcess->getPrefixedId());
        self::assertTrue($OrderInProcess->isPosted());
        self::assertTrue($OrderInProcess->hasInvoice());
        $OrderInProcess->update($PermissionUser);
        $OrderInProcess->save($PermissionUser);
        $OrderInProcess->clear($PermissionUser);
        $OrderInProcess->setSuccessfulStatus();
        $OrderInProcess->setPaymentStatus(2);

        self::assertSame($FinalOrder, $OrderInProcess->createOrder($PermissionUser));
    }

    public function testOrderInProcessReusesExistingOrderWithSameHash(): void
    {
        $PermissionUser = $this->createMock(User::class);
        $PermissionUser->method('getUUID')->willReturn('creator-uuid');

        $FinalOrder = $this->createMock(Order::class);
        $FinalOrder->method('getUUID')->willReturn('shared-uuid');

        $Handler = new TestableHandler();
        $Handler->setResolvedOrder($FinalOrder);
        $this->setHandler($Handler);

        $OrderInProcess = (new ReflectionClass(OrderInProcess::class))->newInstanceWithoutConstructor();
        $this->setProperty($OrderInProcess, 'orderId', null);
        $this->setProperty($OrderInProcess, 'hash', 'shared-uuid');
        $this->setProperty($OrderInProcess, 'cUser', 'creator-uuid');

        self::assertSame($FinalOrder, $OrderInProcess->createOrder($PermissionUser));
    }

    private function createStep(
        string $name,
        string $type
    ): AbstractOrderingStep & MockObject {
        $Step = $this->getMockBuilder(AbstractOrderingStep::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getName', 'validate', 'save', 'getType'])
            ->getMock();
        $Step->method('getName')->willReturn($name);
        $Step->method('getType')->willReturn($type);

        return $Step;
    }

    private function setHandler(TestableHandler $Handler): void
    {
        $instances = $this->getSingletonInstances();
        $instances[Handler::class] = $Handler;
        $this->setSingletonInstances($instances);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function getSingletonInstances(): array
    {
        $Instances = new ReflectionProperty(Singleton::class, 'instances');

        return $Instances->getValue();
    }

    /**
     * @param array<array-key, mixed> $instances
     */
    private function setSingletonInstances(array $instances): void
    {
        $Instances = new ReflectionProperty(Singleton::class, 'instances');
        $Instances->setValue(null, $instances);
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $Property = new ReflectionProperty($object, $name);
        $Property->setValue($object, $value);
    }
}
