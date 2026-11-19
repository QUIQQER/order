<?php

namespace QUI\ERP\Accounting\Invoice;

if (!class_exists(Factory::class, false)) {
    class Factory extends \QUI\Utils\Singleton
    {
        public function createInvoice(mixed $User = null, bool|string $globalProcessId = false): InvoiceTemporary
        {
            return new InvoiceTemporary();
        }
    }
}
