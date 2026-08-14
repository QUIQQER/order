<?php

namespace QUI\ERP\Accounting\Invoice;

if (!class_exists(Handler::class, false)) {
    class Handler extends \QUI\Utils\Singleton
    {
        public function getInvoice(int|string $id): Invoice
        {
            return new Invoice();
        }

        public function getTemporaryInvoice(int|string $id): InvoiceTemporary
        {
            return new InvoiceTemporary();
        }

        public function temporaryInvoiceTable(): string
        {
            return '';
        }
    }
}
