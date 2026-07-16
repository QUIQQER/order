<?php

namespace QUI\ERP\Accounting\Invoice\Utils;

if (!class_exists(Invoice::class, false)) {
    class Invoice
    {
        public static function addressRequirement(): bool
        {
            return false;
        }

        public static function addressRequirementThreshold(): float
        {
            return 0.0;
        }
    }
}
