<?php

namespace QUI\ERP\Order\SimpleCheckout;

if (!class_exists(Settings::class, false)) {
    class Settings
    {
        /**
         * @phpstan-impure
         */
        public static function startGuestCheckout(): void
        {
        }
    }
}
