<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Invoice\Exception as InvoiceException;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Payments\Api\AbstractPayment;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Order\AbstractOrderProcessProvider;
use QUI\ERP\Order\Basket\Basket;
use QUI\ERP\Order\Controls\AbstractOrderingStep;
use QUI\ERP\Order\Controls\OrderProcess\CustomerData;
use QUI\ERP\Order\Controls\OrderProcess\Processing;
use QUI\ERP\Order\Handler;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\Order\Settings;
use QUI\Interfaces\Users\User;
use QUI\Utils\Singleton;
use QUITests\ERP\Order\Fixtures\TestableHandler;
use QUITests\ERP\Order\Fixtures\TestableOrderProcess;
use QUITests\ERP\Order\Fixtures\RenderableOrderStep;
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
        $Handler->setResolvedOrderInProcess($OrderInProcess);
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

    public function testOrderInProcessDelegatesInvoiceAccessToFinalOrder(): void
    {
        $Invoice = $this->createMock(Invoice::class);
        $FinalOrder = $this->createMock(Order::class);
        $FinalOrder->expects(self::once())->method('getInvoice')->willReturn($Invoice);

        $Handler = new TestableHandler();
        $Handler->setResolvedOrder($FinalOrder);
        $this->setHandler($Handler);
        $this->setInvoiceInstalled(true);

        $OrderInProcess = (new ReflectionClass(OrderInProcess::class))->newInstanceWithoutConstructor();
        $this->setProperty($OrderInProcess, 'orderId', 'final-order-uuid');

        self::assertSame($Invoice, $OrderInProcess->getInvoice());
    }

    public function testOrderInProcessRejectsInvoiceAccessWithoutFinalOrder(): void
    {
        $this->setInvoiceInstalled(true);
        $OrderInProcess = (new ReflectionClass(OrderInProcess::class))->newInstanceWithoutConstructor();
        $this->setProperty($OrderInProcess, 'orderId', null);

        $this->expectException(InvoiceException::class);
        $this->expectExceptionCode(404);

        $OrderInProcess->getInvoice();
    }

    public function testOrderInProcessRejectsInvoiceAccessWhenModuleIsUnavailable(): void
    {
        $this->setInvoiceInstalled(false);
        $OrderInProcess = (new ReflectionClass(OrderInProcess::class))->newInstanceWithoutConstructor();
        $this->setProperty($OrderInProcess, 'orderId', null);

        $this->expectException(\QUI\Exception::class);

        $OrderInProcess->getInvoice();
    }

    public function testFinalOrderRejectsInvoiceAccessWhenModuleIsUnavailable(): void
    {
        $this->setInvoiceInstalled(false);
        $Order = (new ReflectionClass(Order::class))->newInstanceWithoutConstructor();

        $this->expectException(\QUI\Exception::class);

        $Order->getInvoice();
    }

    public function testFinalOrderResolvesInvoiceAndReportsPostedStatus(): void
    {
        $Invoice = $this->createMock(Invoice::class);
        $InvoiceHandler = $this->getMockBuilder(InvoiceHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getInvoice'])
            ->getMock();
        $InvoiceHandler->expects(self::exactly(3))
            ->method('getInvoice')
            ->with('invoice-uuid')
            ->willReturn($Invoice);
        $Settings = $this->getMockBuilder(Settings::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isInvoiceInstalled'])
            ->getMock();
        $Settings->method('isInvoiceInstalled')->willReturn(true);
        $this->setSingleton(InvoiceHandler::class, $InvoiceHandler);
        $this->setSingleton(Settings::class, $Settings);
        $Order = (new ReflectionClass(Order::class))->newInstanceWithoutConstructor();
        $this->setProperty($Order, 'invoiceId', 'invoice-uuid');

        self::assertSame($Invoice, $Order->getInvoice());
        self::assertTrue($Order->isPosted());
        self::assertTrue($Order->hasInvoice());
    }

    public function testFinalOrderPostDelegatesInvoiceCreationToSystemUser(): void
    {
        $Invoice = $this->createMock(Invoice::class);
        $Order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createInvoice'])
            ->getMock();
        $Order->expects(self::once())
            ->method('createInvoice')
            ->with(\QUI::getUsers()->getSystemUser())
            ->willReturn($Invoice);

        self::assertSame($Invoice, $Order->post());
    }

    public function testProcessingProviderPausesOrderCreation(): void
    {
        $OrderInProcess = $this->createMock(OrderInProcess::class);
        $OrderInProcess->expects(self::never())->method('createOrder');
        $OrderInProcess->expects(self::never())->method('delete');
        $Provider = $this->createMock(AbstractOrderProcessProvider::class);
        $Provider->expects(self::once())
            ->method('onOrderStart')
            ->with($OrderInProcess)
            ->willReturn(AbstractOrderProcessProvider::PROCESSING_STATUS_PROCESSING);
        $Provider->expects(self::never())->method('onOrderSuccess');
        $Provider->expects(self::never())->method('onOrderAbort');
        $Handler = new TestableHandler();
        $Handler->setOrderProcessProviders([$Provider]);
        $this->setHandler($Handler);
        $Settings = $this->getMockBuilder(Settings::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $Settings->method('get')->with('order', 'failedPaymentProcedure')->willReturn('retry');
        $this->setSingleton(Settings::class, $Settings);
        $Process = new TestableOrderProcess();
        $Process->setTestOrder($OrderInProcess);
        $Process->setTestSteps([
            'Checkout' => $this->createStep(
                'Checkout',
                \QUI\ERP\Order\Controls\OrderProcess\Checkout::class
            )
        ]);

        $Process->invokeSend();

        self::assertSame($OrderInProcess, $Process->getOrder());
        self::assertSame(0, $Process->getCleanupCalls());
    }

    public function testCleanupRemovesResolvedProcessOrderAndCompletesBasket(): void
    {
        $FinalOrder = $this->createMock(Order::class);
        $FinalOrder->method('isSuccessful')->willReturn(1);
        $FinalOrder->method('getUUID')->willReturn('cleanup-uuid');
        $ProcessOrder = $this->createMock(OrderInProcess::class);
        $ProcessOrder->method('getUUID')->willReturn('cleanup-uuid');
        $ProcessOrder->expects(self::once())->method('delete');
        $Basket = $this->createMock(Basket::class);
        $Basket->expects(self::once())->method('successful');
        $Basket->expects(self::once())->method('save');
        $Handler = new TestableHandler();
        $Handler->setResolvedOrder($FinalOrder);
        $Handler->setResolvedOrderInProcess($ProcessOrder);
        $Handler->setResolvedBasket($Basket);
        $this->setHandler($Handler);
        $Process = new TestableOrderProcess();
        $Process->setTestOrder($FinalOrder);

        $Process->invokeCleanup();

        self::assertSame($FinalOrder, $Process->getOrder());
    }

    public function testNonGatewayPaymentCompletesOrderFromProcessingCheck(): void
    {
        $PaymentType = $this->createMock(AbstractPayment::class);
        $PaymentType->method('isGateway')->willReturn(false);
        $Payment = $this->createMock(Payment::class);
        $Payment->method('getPaymentType')->willReturn($PaymentType);
        $FinalOrder = $this->createMock(Order::class);
        $FinalOrder->method('getUUID')->willReturn('completed-order-uuid');
        $OrderInProcess = $this->createMock(OrderInProcess::class);
        $OrderInProcess->method('getUUID')->willReturn('processing-order-uuid');
        $OrderInProcess->method('getPayment')->willReturn($Payment);
        $OrderInProcess->method('isSuccessful')->willReturn(0);
        $OrderInProcess->expects(self::once())->method('createOrder')->willReturn($FinalOrder);
        $OrderInProcess->expects(self::once())->method('delete');
        $Handler = new TestableHandler();
        $Handler->setOrderProcessProviders([]);
        $this->setHandler($Handler);
        $Checkout = $this->createStep('Checkout', \QUI\ERP\Order\Controls\OrderProcess\Checkout::class);
        $Finish = $this->createStep('Finish', \QUI\ERP\Order\Controls\OrderProcess\Finish::class);
        $Processing = $this->createStep('Processing', Processing::class);
        $Process = new TestableOrderProcess();
        $Process->setTestOrder($OrderInProcess);
        $Process->setTestSteps([
            'Checkout' => $Checkout,
            'Finish' => $Finish
        ]);
        $Process->setTestProcessingStep($Processing);
        $Process->setAttribute('step', 'Checkout');
        $Session = \QUI::getSession();
        $termsKey = 'termsAndConditions-processing-order-uuid';
        $originalTerms = $Session->get($termsKey);
        $request = $_REQUEST;

        try {
            $Session->set($termsKey, 1);
            $_REQUEST['payableToOrder'] = 1;

            self::assertFalse($Process->invokeCheckProcessing());
            self::assertSame($FinalOrder, $Process->getOrder());
            self::assertSame('Finish', $Process->getAttribute('step'));
            self::assertSame('completed-order-uuid', $Process->getAttribute('orderHash'));
            self::assertSame(1, $Process->getCleanupCalls());
        } finally {
            $Session->set($termsKey, $originalTerms);
            $_REQUEST = $request;
        }
    }

    public function testGatewayPaymentRendersProcessingProviderWithoutCreatingOrder(): void
    {
        $PaymentType = $this->createMock(AbstractPayment::class);
        $PaymentType->method('isGateway')->willReturn(true);
        $Payment = $this->createMock(Payment::class);
        $Payment->method('getPaymentType')->willReturn($PaymentType);
        $Payment->method('getId')->willReturn(7);
        $Articles = $this->createMock(ArticleList::class);
        $Articles->method('toArray')->willReturn(['articles' => []]);
        $OrderInProcess = $this->createMock(OrderInProcess::class);
        $OrderInProcess->method('getUUID')->willReturn('gateway-order-uuid');
        $OrderInProcess->method('getPayment')->willReturn($Payment);
        $OrderInProcess->method('getArticles')->willReturn($Articles);
        $OrderInProcess->method('isSuccessful')->willReturn(0);
        $OrderInProcess->expects(self::never())->method('createOrder');
        $OrderInProcess->expects(self::never())->method('delete');
        $Provider = $this->createMock(AbstractOrderProcessProvider::class);
        $Provider->expects(self::once())->method('initSteps');
        $Provider->expects(self::once())
            ->method('onOrderStart')
            ->with($OrderInProcess)
            ->willReturn(AbstractOrderProcessProvider::PROCESSING_STATUS_PROCESSING);
        $Provider->method('getDisplay')->willReturn('<div class="gateway-provider">Gateway</div>');
        $Provider->method('hasErrors')->willReturn(false);
        $Handler = new TestableHandler();
        $Handler->setOrderProcessProviders([$Provider]);
        $Handler->setResolvedOrderInProcess($OrderInProcess);
        $this->setHandler($Handler);
        $Settings = $this->getMockBuilder(Settings::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $Settings->method('get')->willReturnCallback(
            static fn(string $section, int | string $key): bool | string =>
                $section === 'order' && $key === 'failedPaymentProcedure' ? 'retry' : false
        );
        $this->setSingleton(Settings::class, $Settings);
        $Checkout = new RenderableOrderStep(
            'Checkout',
            \QUI\ERP\Order\Controls\OrderProcess\Checkout::class
        );
        $Finish = new RenderableOrderStep(
            'Finish',
            \QUI\ERP\Order\Controls\OrderProcess\Finish::class
        );
        $Processing = new Processing([
            'Order' => $OrderInProcess,
            'priority' => 40
        ]);
        $Basket = $this->createMock(Basket::class);
        $OrderProcessSite = $this->createMock(\QUI\Interfaces\Projects\Site::class);
        $OrderProcessSite->method('getUrlRewritten')->willReturn('/order');
        $Project = $this->createMock(\QUI\Projects\Project::class);
        $Project->method('getSites')->willReturn([$OrderProcessSite]);
        $Site = $this->createMock(\QUI\Interfaces\Projects\Site::class);
        $Site->method('getProject')->willReturn($Project);
        $Process = new TestableOrderProcess();
        $Process->setTestOrder($OrderInProcess);
        $Process->setTestSteps([
            'Checkout' => $Checkout,
            'Finish' => $Finish
        ]);
        $Process->setTestProcessingStep($Processing);
        $Process->setTestBasket($Basket);
        $Process->setAttribute('Site', $Site);
        $Process->setAttribute('step', 'Checkout');
        $Process->setAttribute('backToShopUrl', '/shop');
        $Users = \QUI::getUsers();
        $SessionUser = new ReflectionProperty($Users, 'Session');
        $originalUser = $SessionUser->getValue($Users);
        $Session = \QUI::getSession();
        $termsKey = 'termsAndConditions-gateway-order-uuid';
        $originalTerms = $Session->get($termsKey);
        $request = $_REQUEST;

        try {
            $SessionUser->setValue($Users, $Users->getSystemUser());
            $Session->set($termsKey, 1);
            $_REQUEST['payableToOrder'] = 1;
            $result = $Process->invokeCheckProcessing();

            self::assertIsString($result);
            self::assertStringContainsString('gateway-provider', $result);
            self::assertSame('Processing', $Process->getAttribute('step'));
            self::assertSame($OrderInProcess, $Process->getOrder());
            self::assertSame(0, $Process->getCleanupCalls());
        } finally {
            $SessionUser->setValue($Users, $originalUser);
            $Session->set($termsKey, $originalTerms);
            $_REQUEST = $request;
        }
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
        $this->setSingleton(Handler::class, $Handler);
    }

    private function setInvoiceInstalled(bool $isInstalled): void
    {
        $Settings = $this->getMockBuilder(Settings::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isInvoiceInstalled'])
            ->getMock();
        $Settings->method('isInvoiceInstalled')->willReturn($isInstalled);
        $this->setSingleton(Settings::class, $Settings);
    }

    private function setSingleton(string $class, object $instance): void
    {
        $instances = $this->getSingletonInstances();
        $instances[$class] = $instance;
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
