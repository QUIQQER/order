<?php

namespace QUITests\ERP\Order\ProcessingStatus;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\ProcessingStatus\StatusUnknown;

class StatusUnknownUnitTest extends TestCase
{
    public function testDefaultValuesAreStable(): void
    {
        $Status = new StatusUnknown();

        $this->assertSame(0, $Status->getId());
        $this->assertSame('#999', $Status->getColor());
        $this->assertFalse($Status->isAutoNotification());
    }
}
