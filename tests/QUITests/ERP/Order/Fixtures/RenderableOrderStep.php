<?php

namespace QUITests\ERP\Order\Fixtures;

use QUI;
use QUI\ERP\Order\Controls\AbstractOrderingStep;

class RenderableOrderStep extends AbstractOrderingStep
{
    public function __construct(
        private readonly string $stepName,
        private readonly string $stepType
    ) {
        parent::__construct();
    }

    public function getName(null | QUI\Locale $Locale = null): string
    {
        return $this->stepName;
    }

    public function getType(): string
    {
        return $this->stepType;
    }

    public function validate(): void
    {
    }

    public function save(): void
    {
    }

    public function getBody(): string
    {
        return '<div class="renderable-order-step">' . $this->stepName . '</div>';
    }
}
