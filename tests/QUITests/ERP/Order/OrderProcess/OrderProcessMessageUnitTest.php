<?php

namespace QUITests\ERP\Order\OrderProcess;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\OrderProcess\OrderProcessMessage;

class OrderProcessMessageUnitTest extends TestCase
{
    public function testDefaultTypeIsInfo(): void
    {
        $Message = new OrderProcessMessage('hello');

        $this->assertSame('hello', $Message->getMsg());
        $this->assertSame(OrderProcessMessage::MESSAGE_TYPE_INFO, $Message->getType());
    }

    public function testCustomTypeIsStored(): void
    {
        $Message = new OrderProcessMessage(
            'boom',
            OrderProcessMessage::MESSAGE_TYPE_ERROR
        );

        $this->assertSame('boom', $Message->getMsg());
        $this->assertSame(OrderProcessMessage::MESSAGE_TYPE_ERROR, $Message->getType());
    }

    public function testSuccessTypeConstantCanBeUsed(): void
    {
        $Message = new OrderProcessMessage(
            'done',
            OrderProcessMessage::MESSAGE_TYPE_SUCCESS
        );

        $this->assertSame(OrderProcessMessage::MESSAGE_TYPE_SUCCESS, $Message->getType());
    }
}
