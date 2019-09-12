<?php

namespace QUI\ERP\Order\OrderProcess;

/**
 * Interface OrderProcessMessageHandlerInterface
 *
 * Interface for classes that can provide a message by ID for the purpose of showing it
 * at any step in the order process (once).
 */
interface OrderProcessMessageHandlerInterface
{
    /**
     * @param int $id
     * @return OrderProcessMessage
     */
    public static function getMessage(int $id);
}
