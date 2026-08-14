<?php

namespace QUITests\ERP\Order\Fixtures;

use LogicException;
use QUI;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Basket\Basket;
use QUI\ERP\Order\Basket\BasketGuest;
use QUI\ERP\Order\Basket\BasketOrder;
use QUI\ERP\Order\Controls\AbstractOrderingStep;
use QUI\ERP\Order\OrderProcess;
use QUI\ERP\Order\OrderProcess\OrderProcessMessage;
use QUI\ERP\Order\Utils\OrderProcessSteps;

class TestableOrderProcess extends OrderProcess
{
    private ?AbstractOrderingStep $testProcessingStep = null;
    private Basket | BasketGuest | BasketOrder | null $testBasket = null;
    private string $testUrl = '/checkout';
    private int $cleanupCalls = 0;

    public function __construct()
    {
        $this->setAttributes([
            'Site' => false,
            'step' => false,
            'orderHash' => false,
            'backToShopUrl' => false
        ]);

        $this->Events = new QUI\Events\Event();
    }

    public function setTestOrder(?AbstractOrder $Order): void
    {
        $this->Order = $Order;
    }

    public function getOrder(): ?AbstractOrder
    {
        return $this->Order;
    }

    /**
     * @param array<string, AbstractOrderingStep> $steps
     */
    public function setTestSteps(array $steps): void
    {
        $this->steps = $steps;
    }

    public function setTestProcessingStep(AbstractOrderingStep $ProcessingStep): void
    {
        $this->testProcessingStep = $ProcessingStep;
    }

    public function setTestBasket(Basket | BasketGuest | BasketOrder $Basket): void
    {
        $this->testBasket = $Basket;
    }

    protected function getProcessingStep(): AbstractOrderingStep
    {
        if ($this->testProcessingStep === null) {
            throw new LogicException('A processing step is required for this test fixture.');
        }

        return $this->testProcessingStep;
    }

    protected function getBasket(): BasketGuest | Basket | BasketOrder
    {
        if ($this->testBasket !== null) {
            return $this->testBasket;
        }

        return parent::getBasket();
    }

    public function setTestUrl(string $url): void
    {
        $this->testUrl = $url;
    }

    public function getUrl(): string
    {
        return $this->testUrl;
    }

    protected function cleanup(): void
    {
        $this->cleanupCalls++;
    }

    public function getCleanupCalls(): int
    {
        return $this->cleanupCalls;
    }

    public function invokeGetStepByName(string $name): false | AbstractOrderingStep
    {
        return $this->getStepByName($name);
    }

    public function invokeGetCurrentStepName(): string
    {
        return $this->getCurrentStepName();
    }

    public function invokeGetNextStepName(?AbstractOrderingStep $StartStep = null): bool | string
    {
        return $this->getNextStepName($StartStep);
    }

    public function invokeGetPreviousStepName(?AbstractOrderingStep $StartStep = null): bool | string
    {
        return $this->getPreviousStepName($StartStep);
    }

    /**
     * @return array<string, AbstractOrderingStep>
     */
    public function invokeParseStepsToArray(OrderProcessSteps $Steps): array
    {
        return $this->parseStepsToArray($Steps);
    }

    public function invokeSortSteps(OrderProcessSteps $Steps): void
    {
        $this->sortSteps($Steps);
    }

    public function invokeCheckSubmission(): void
    {
        $this->checkSubmission();
    }

    public function invokeCheckSuccessfulStatus(): void
    {
        $this->checkSuccessfulStatus();
    }

    public function invokeCheckProcessing(): false | string
    {
        return $this->checkProcessing();
    }

    public function invokeSend(): void
    {
        $this->send();
    }

    public function invokeCleanup(): void
    {
        parent::cleanup();
    }

    /**
     * @return OrderProcessMessage[]
     */
    public function invokeGetStepMessages(string $orderStep): array
    {
        return $this->getStepMessages($orderStep);
    }

    public function invokeBaseGetBasket(): BasketGuest | Basket | BasketOrder
    {
        return parent::getBasket();
    }

    public function invokeBaseGetOrder(): ?AbstractOrder
    {
        return parent::getOrder();
    }

    public function invokeBaseGetProcessingStep(): AbstractOrderingStep
    {
        return parent::getProcessingStep();
    }

    public function invokeParseSteps(): OrderProcessSteps
    {
        return parent::parseSteps();
    }

    public function invokeBaseGetUrl(): string
    {
        return parent::getUrl();
    }

    public function invokeExecutePayableStatus(): false | string
    {
        return $this->executePayableStatus();
    }
}
