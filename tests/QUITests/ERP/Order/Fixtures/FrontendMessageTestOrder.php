<?php

namespace QUITests\ERP\Order\Fixtures;

use QUI\ERP\Order\Order;

class FrontendMessageTestOrder extends Order
{
    public int $frontendMessageSaveCalls = 0;

    public function __construct()
    {
    }

    protected function saveFrontendMessages(): void
    {
        $this->frontendMessageSaveCalls++;
    }
}
