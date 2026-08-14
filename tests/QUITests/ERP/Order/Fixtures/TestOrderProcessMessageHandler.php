<?php

namespace QUITests\ERP\Order\Fixtures;

use QUI\ERP\Order\OrderProcess\OrderProcessMessage;
use QUI\ERP\Order\OrderProcess\OrderProcessMessageHandlerInterface;

class TestOrderProcessMessageHandler implements OrderProcessMessageHandlerInterface
{
    public static function getMessage(int $id): OrderProcessMessage
    {
        return new OrderProcessMessage('message-' . $id);
    }
}
