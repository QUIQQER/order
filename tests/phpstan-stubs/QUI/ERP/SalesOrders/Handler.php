<?php

namespace QUI\ERP\SalesOrders;

if (!class_exists(Handler::class, false)) {
    class Handler
    {
        public const PERMISSION_UPDATE = 'erp.salesorders.update';

        public static function createSalesOrder(mixed $User = null, bool|string $globalProcessId = false): SalesOrder
        {
            return new SalesOrder();
        }
    }
}
