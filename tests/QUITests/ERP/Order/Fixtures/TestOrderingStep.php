<?php

namespace QUITests\ERP\Order\Fixtures;

use QUI;
use QUI\ERP\Order\Controls\AbstractOrderingStep;

class TestOrderingStep extends AbstractOrderingStep
{
    public bool $failValidation = false;

    public function getName(null | QUI\Locale $Locale = null): string
    {
        return 'Test';
    }

    public function validate(): void
    {
        if ($this->failValidation) {
            throw new QUI\ERP\Order\Exception('Invalid test step');
        }
    }

    public function save(): void
    {
    }
}
