<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\Factory;

class FactoryUnitTest extends TestCase
{
    public function testGetOrderConstructNeedlesContainsExpectedEntries(): void
    {
        $needles = Factory::getInstance()->getOrderConstructNeedles();

        $this->assertContains('id', $needles);
        $this->assertContains('status', $needles);
        $this->assertContains('customerId', $needles);
        $this->assertContains('addressInvoice', $needles);
        $this->assertContains('addressDelivery', $needles);
        $this->assertContains('payment_method', $needles);
        $this->assertContains('payment_data', $needles);
        $this->assertContains('hash', $needles);
        $this->assertContains('c_date', $needles);
        $this->assertContains('c_user', $needles);
        $this->assertCount(15, $needles);
        $this->assertSame('id', $needles[0]);
        $this->assertSame(2, count(array_keys($needles, 'addressInvoice', true)));
    }
}
