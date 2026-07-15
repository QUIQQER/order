<?php

namespace QUI\ERP\SalesOrders;

if (!class_exists(Handler::class)) {
    class Handler
    {
        public static function createSalesOrder(mixed $User = null, bool|string $globalProcessId = false): SalesOrder
        {
            return new SalesOrder();
        }
    }
}
