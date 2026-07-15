<?php

namespace QUI\ERP\Accounting\Invoice;

if (!class_exists(Invoice::class)) {
    class Invoice
    {
        public function getUUID(): string
        {
            return '';
        }

        public function getType(): int
        {
            return 0;
        }

        public function getAttribute(string $name): mixed
        {
            return null;
        }
    }
}
