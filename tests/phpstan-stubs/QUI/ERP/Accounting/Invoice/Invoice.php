<?php

namespace QUI\ERP\Accounting\Invoice;

if (!class_exists(Invoice::class, false)) {
    class Invoice extends \QUI\QDOM
    {
        public function getUUID(): string
        {
            return '';
        }

        public function getGlobalProcessId(): string
        {
            return '';
        }
    }
}
