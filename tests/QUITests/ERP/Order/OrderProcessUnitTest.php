<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Comments;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Controls\AbstractOrderingStep;
use QUI\ERP\Order\Controls\OrderProcess\Checkout;
use QUI\ERP\Order\Controls\OrderProcess\CustomerData;
use QUI\ERP\Order\Controls\OrderProcess\Finish;
use QUI\ERP\Order\Controls\OrderProcess\Processing;
use QUI\ERP\Order\OrderProcess;
use QUI\ERP\Order\Utils\OrderProcessSteps;
use QUITests\ERP\Order\Fixtures\ConstructableOrderProcess;
use QUITests\ERP\Order\Fixtures\TestableOrderProcess;
use QUITests\ERP\Order\Fixtures\TestOrderProcessMessageHandler;
use QUITests\ERP\Order\Fixtures\RenderableOrderStep;
use ReflectionClass;
use ReflectionProperty;

class OrderProcessUnitTest extends TestCase
{
    public function testLoggedInConstructorImportsBasketAndSelectsRequestedStep(): void
    {
        $Users = QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $originalUser = $Session->getValue($Users);
        $request = $_REQUEST;
        $SystemUser = $Users->getSystemUser();
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getUUID')->willReturn((string)$SystemUser->getUUID());
        $Order = $this->createMock(QUI\ERP\Order\OrderInProcess::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getUUID')->willReturn('constructor-order-uuid');
        $Order->method('isSuccessful')->willReturn(0);
        $Order->method('getCurrency')->willReturn(QUI\ERP\Currency\Handler::getRuntimeCurrency());
        $Basket = $this->createMock(QUI\ERP\Order\Basket\Basket::class);
        $Basket->expects(self::exactly(3))->method('toOrder')->with($Order);
        $Basket->method('getId')->willReturn(77);
        $CustomerData = new RenderableOrderStep('CustomerData', CustomerData::class);
        $Finish = new RenderableOrderStep('Finish', Finish::class);
        $Processing = new RenderableOrderStep('Processing', Processing::class);

        try {
            $Session->setValue($Users, $SystemUser);
            $_REQUEST['step'] = 'CustomerData';
            $Process = new ConstructableOrderProcess(
                $Order,
                $Basket,
                [
                    'CustomerData' => $CustomerData,
                    'Finish' => $Finish
                ],
                $Processing
            );

            self::assertSame(77, $Process->getAttribute('basketId'));
            self::assertSame('constructor-order-uuid', $Process->getAttribute('orderHash'));
            self::assertSame('CustomerData', $Process->getAttribute('step'));
            self::assertSame($Order, $Process->getOrder());

            $_REQUEST['step'] = 'Processing';
            $ProcessingProcess = new ConstructableOrderProcess(
                $Order,
                $Basket,
                [
                    'CustomerData' => $CustomerData,
                    'Finish' => $Finish
                ],
                $Processing
            );
            self::assertSame('Processing', $ProcessingProcess->getAttribute('step'));

            $_REQUEST['step'] = 'missing-step';
            $FallbackProcess = new ConstructableOrderProcess(
                $Order,
                $Basket,
                [
                    'CustomerData' => $CustomerData,
                    'Finish' => $Finish
                ],
                $Processing
            );
            self::assertSame('CustomerData', $FallbackProcess->getAttribute('step'));
        } finally {
            $Session->setValue($Users, $originalUser);
            $_REQUEST = $request;
        }
    }

    public function testLoggedInConstructorMovesSuccessfulOrderToFinish(): void
    {
        $Users = QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $originalUser = $Session->getValue($Users);
        $SystemUser = $Users->getSystemUser();
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getUUID')->willReturn((string)$SystemUser->getUUID());
        $Order = $this->createMock(QUI\ERP\Order\Order::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getUUID')->willReturn('successful-constructor-order');
        $Order->method('isSuccessful')->willReturn(1);
        $Basket = $this->createMock(QUI\ERP\Order\Basket\Basket::class);
        $Finish = new RenderableOrderStep('Finish', Finish::class);
        $Processing = new RenderableOrderStep('Processing', Processing::class);

        try {
            $Session->setValue($Users, $SystemUser);
            $Process = new ConstructableOrderProcess(
                $Order,
                $Basket,
                ['Finish' => $Finish],
                $Processing
            );

            self::assertSame('Finish', $Process->getAttribute('step'));
            self::assertSame('successful-constructor-order', $Process->getAttribute('orderHash'));
        } finally {
            $Session->setValue($Users, $originalUser);
        }
    }

    public function testGuestProcessConstructorAndBodyRenderLoginSelection(): void
    {
        $Users = QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $originalUser = $Session->getValue($Users);
        $Site = $this->createMock(QUI\Interfaces\Projects\Site::class);
        $Site->method('getAttribute')->willReturn(false);

        try {
            $Session->setValue($Users, $Users->getNobody());
            $Process = new OrderProcess(['Site' => $Site]);

            self::assertFalse($Process->getAttribute('orderHash'));
            self::assertTrue($Process->getAttribute('basket'));
            self::assertTrue($Process->getAttribute('basketEditable'));
            self::assertStringContainsString('quiqqer-order', $Process->getBody());
        } finally {
            $Session->setValue($Users, $originalUser);
        }
    }

    public function testGuestAndSuccessfulOrdersBuildMinimalStepFlows(): void
    {
        $Users = QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $originalUser = $Session->getValue($Users);

        try {
            $Session->setValue($Users, $Users->getNobody());
            $GuestProcess = new TestableOrderProcess();
            $guestSteps = iterator_to_array($GuestProcess->invokeParseSteps());

            self::assertCount(1, $guestSteps);
            self::assertInstanceOf(
                QUI\ERP\Order\Controls\OrderProcess\Registration::class,
                $guestSteps[0]
            );

            $Session->setValue($Users, $Users->getSystemUser());
            $FinalOrder = $this->createMock(QUI\ERP\Order\Order::class);
            $FinalOrder->method('isSuccessful')->willReturn(1);
            $SuccessfulProcess = new TestableOrderProcess();
            $SuccessfulProcess->setTestOrder($FinalOrder);
            $successfulSteps = iterator_to_array($SuccessfulProcess->invokeParseSteps());

            self::assertCount(1, $successfulSteps);
            self::assertInstanceOf(Finish::class, $successfulSteps[0]);
        } finally {
            $Session->setValue($Users, $originalUser);
        }
    }

    public function testBaseProcessingStepResolutionUsesConfiguredStepOrCreatesFallback(): void
    {
        $ConfiguredProcessing = new RenderableOrderStep('Processing', Processing::class);
        $Process = new TestableOrderProcess();
        $Process->setTestSteps(['Processing' => $ConfiguredProcessing]);

        self::assertSame($ConfiguredProcessing, $Process->invokeBaseGetProcessingStep());

        $Order = $this->createMock(AbstractOrder::class);
        $Process->setTestOrder($Order);
        $Process->setTestSteps([
            'Finish' => new RenderableOrderStep('Finish', Finish::class)
        ]);

        self::assertInstanceOf(Processing::class, $Process->invokeBaseGetProcessingStep());
    }

    public function testBaseGuestBasketOrderAndUrlFallbacks(): void
    {
        $Users = QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $originalUser = $Session->getValue($Users);
        $Process = new TestableOrderProcess();
        $Site = $this->createMock(QUI\Interfaces\Projects\Site::class);
        $Site->method('getUrlRewritten')->willReturn('/checkout');
        $Project = $this->createMock(QUI\Projects\Project::class);
        $Project->method('getSites')->willReturn([$Site]);
        $Process->setAttribute('Project', $Project);

        try {
            $Session->setValue($Users, $Users->getNobody());

            self::assertInstanceOf(
                QUI\ERP\Order\Basket\BasketGuest::class,
                $Process->invokeBaseGetBasket()
            );
            self::assertNull($Process->invokeBaseGetOrder());
            self::assertSame('/checkout', $Process->invokeBaseGetUrl());
        } finally {
            $Session->setValue($Users, $originalUser);
        }
    }

    public function testSiteResolutionHandlesMissingConfiguredAndFallbackSites(): void
    {
        $originalRewrite = QUI::$Rewrite;
        $ConfiguredSite = $this->createMock(QUI\Projects\Site::class);
        $FallbackSite = $this->createMock(QUI\Projects\Site::class);
        $ConfiguredProject = $this->createMock(QUI\Projects\Project::class);
        $ConfiguredProject->method('getSitesIds')->willReturn([['id' => 42]]);
        $ConfiguredProject->method('get')->with(42)->willReturn($ConfiguredSite);
        $FallbackProject = $this->createMock(QUI\Projects\Project::class);
        $FallbackProject->method('getSitesIds')->willReturn([]);
        $FallbackProject->method('firstChild')->willReturn($FallbackSite);
        $Rewrite = $this->createMock(QUI\Rewrite::class);
        $Rewrite->method('getProject')->willReturnOnConsecutiveCalls(
            null,
            $ConfiguredProject,
            $FallbackProject
        );
        QUI::$Rewrite = $Rewrite;

        try {
            try {
                (new TestableOrderProcess())->getSite();
                self::fail('A missing rewrite project must reject site resolution.');
            } catch (QUI\ERP\Order\Exception) {
                self::assertTrue(true);
            }

            $ConfiguredProcess = new TestableOrderProcess();
            self::assertSame($ConfiguredSite, $ConfiguredProcess->getSite());
            self::assertSame($ConfiguredSite, $ConfiguredProcess->getSite());
            self::assertSame($FallbackSite, (new TestableOrderProcess())->getSite());
        } finally {
            QUI::$Rewrite = $originalRewrite;
        }
    }

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

    public function testSuccessfulFinalOrderRendersFinishAndClearsBasket(): void
    {
        $Users = QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $originalUser = $Session->getValue($Users);
        $Order = $this->createMock(\QUI\ERP\Order\Order::class);
        $Order->method('isSuccessful')->willReturn(1);
        $Order->method('getUUID')->willReturn('rendered-order-uuid');
        $Order->method('getPayment')->willReturn(null);
        $Order->method('count')->willReturn(0);
        $Order->method('getId')->willReturn(42);
        $Basket = $this->createMock(\QUI\ERP\Order\Basket\Basket::class);
        $Basket->expects(self::once())->method('clear');
        $Finish = new RenderableOrderStep('Finish', Finish::class);
        $Processing = $this->createProcessingStep();
        $Site = $this->createMock(QUI\Interfaces\Projects\Site::class);
        $Site->method('getProject')->willReturn(
            $this->createMock(QUI\Projects\Project::class)
        );
        $Process = $this->createProcess($Order, [$Finish], $Processing);
        $Process->setTestBasket($Basket);
        $Process->setAttribute('Site', $Site);
        $Process->setAttribute('step', 'Finish');
        $Process->setAttribute('backToShopUrl', '/shop');

        try {
            $Session->setValue($Users, $Users->getSystemUser());
            $body = $Process->getBody();

            self::assertStringContainsString('renderable-order-step', $body);
            self::assertStringContainsString('data-order-hash=""', $body);
            self::assertStringContainsString('name="orderId" value="42"', $body);
            self::assertSame('rendered-order-uuid', $Process->getAttribute('orderHash'));
            self::assertNull($Process->getOrder());
        } finally {
            $Session->setValue($Users, $originalUser);
        }
    }

    public function testLoggedInProcessRendersCurrentStepAndConsumesFrontendMessages(): void
    {
        $Users = QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $originalUser = $Session->getValue($Users);
        $FrontendMessages = new Comments();
        $FrontendMessages->addComment('Persisted process notice');
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getUUID')->willReturn('active-order-uuid');
        $Order->method('getPayment')->willReturn(null);
        $Order->method('isSuccessful')->willReturn(0);
        $Order->method('getFrontendMessages')->willReturn($FrontendMessages);
        $Order->expects(self::once())->method('clearFrontendMessages');
        $Basket = new RenderableOrderStep('Basket', AbstractOrderingStep::class);
        $CustomerData = new RenderableOrderStep('CustomerData', CustomerData::class);
        $Checkout = new RenderableOrderStep('Checkout', Checkout::class);
        $Finish = new RenderableOrderStep('Finish', Finish::class);
        $Processing = $this->createProcessingStep();
        $OrderProcessSite = $this->createMock(QUI\Interfaces\Projects\Site::class);
        $OrderProcessSite->method('getUrlRewritten')->willReturn('/order');
        $Project = $this->createMock(QUI\Projects\Project::class);
        $Project->method('getSites')->willReturn([$OrderProcessSite]);
        $Site = $this->createMock(QUI\Interfaces\Projects\Site::class);
        $Site->method('getProject')->willReturn($Project);
        $Process = $this->createProcess(
            $Order,
            [$Basket, $CustomerData, $Checkout, $Finish],
            $Processing
        );
        $Process->setAttribute('Site', $Site);
        $Process->setAttribute('step', 'CustomerData');
        $Process->setAttribute('orderHash', true);
        $Process->setAttribute('backToShopUrl', '/shop');

        try {
            $Session->setValue($Users, $Users->getSystemUser());
            $body = $Process->getBody();

            self::assertStringContainsString('renderable-order-step">CustomerData', $body);
            self::assertStringContainsString('Persisted process notice', $body);
            self::assertStringContainsString('/checkout/Checkout/active-order-uuid', $body);
            self::assertSame('/order', $Process->getAttribute('data-url'));
            self::assertSame('CustomerData', $Process->getAttribute('step'));
        } finally {
            $Session->setValue($Users, $originalUser);
        }
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

            $Failing = $this->createStep('Failing', CustomerData::class);
            $Failing->method('save')->willThrowException(new QUI\Exception('Expected test failure'));
            $FailingProcess = $this->createProcess($Order, [$Failing], $Processing);
            $_REQUEST['current'] = 'Failing';
            $FailingProcess->invokeCheckSubmission();

            self::assertTrue(true);
        } finally {
            $_REQUEST = $request;
        }
    }

    public function testSuccessfulPaymentMarksOrderSuccessfulOnProcessingStep(): void
    {
        $Payment = $this->createMock(Payment::class);
        $Payment->expects(self::once())
            ->method('isSuccessful')
            ->with('payment-order-uuid')
            ->willReturn(true);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getUUID')->willReturn('payment-order-uuid');
        $Order->method('getPayment')->willReturn($Payment);
        $Order->expects(self::once())->method('setSuccessfulStatus');
        $Processing = $this->createProcessingStep();
        $Process = $this->createProcess($Order, [$this->createStep('Basket')], $Processing);
        $Process->setAttribute('step', 'Processing');

        $Process->invokeCheckSuccessfulStatus();
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

    private function createProcessingStep(): Processing & MockObject
    {
        $Step = $this->getMockBuilder(Processing::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getName', 'validate', 'save', 'getType'])
            ->getMock();
        $Step->method('getName')->willReturn('Processing');
        $Step->method('getType')->willReturn(Processing::class);

        return $Step;
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $Property = new ReflectionProperty($object, $name);
        $Property->setValue($object, $value);
    }
}
