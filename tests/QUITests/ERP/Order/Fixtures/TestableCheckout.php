<?php

namespace QUITests\ERP\Order\Fixtures;

use QUI\ERP\Order\Controls\OrderProcess\Checkout;

class TestableCheckout extends Checkout
{
    /** @var array<string, string> */
    public array $links = [];

    public function getLinkOf(string $config): string
    {
        return $this->links[$config] ?? '';
    }
}
