<?php

namespace QUI\ERP\Shipping\ShippingStatus;

if (!class_exists(Status::class, false)) {
    class Status
    {
        public function getId(): int
        {
            return 0;
        }

        public function getTitle(?\QUI\Locale $Locale = null): string
        {
            return '';
        }

        public function getColor(): string
        {
            return '';
        }
    }
}
