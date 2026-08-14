<?php

namespace QUITests\ERP\Order\Fixtures;

use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Basket\Basket;
use QUI\ERP\Order\Basket\BasketGuest;
use QUI\ERP\Order\Basket\BasketOrder;
use QUI\ERP\Order\Controls\AbstractOrderingStep;
use QUI\ERP\Order\OrderProcess;

class ConstructableOrderProcess extends OrderProcess
{
    /** @var array<string, AbstractOrderingStep> */
    private array $constructorSteps;
    private AbstractOrderingStep $constructorProcessingStep;
    private Basket | BasketGuest | BasketOrder $constructorBasket;

    /**
     * @param array<string, AbstractOrderingStep> $steps
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        AbstractOrder $Order,
        Basket | BasketGuest | BasketOrder $Basket,
        array $steps,
        AbstractOrderingStep $ProcessingStep,
        array $attributes = []
    ) {
        $this->constructorBasket = $Basket;
        $this->constructorSteps = $steps;
        $this->constructorProcessingStep = $ProcessingStep;

        parent::__construct(array_merge($attributes, ['Order' => $Order]));
    }

    /**
     * @return array<string, AbstractOrderingStep>
     */
    public function getSteps(): array
    {
        return $this->constructorSteps;
    }

    protected function getBasket(): BasketGuest | Basket | BasketOrder
    {
        return $this->constructorBasket;
    }

    protected function getProcessingStep(): AbstractOrderingStep
    {
        return $this->constructorProcessingStep;
    }
}
