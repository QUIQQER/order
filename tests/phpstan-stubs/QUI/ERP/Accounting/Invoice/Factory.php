<?php

namespace QUI\ERP\Accounting\Invoice;

if (!class_exists(Factory::class, false)) {
    class Factory
    {
        public static function getInstance(): self
        {
            return new self();
        }

        public function createInvoice(mixed $User = null, bool|string $globalProcessId = false): InvoiceTemporary
        {
            return new InvoiceTemporary();
        }
    }
}
