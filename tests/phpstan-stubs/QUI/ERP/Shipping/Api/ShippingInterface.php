<?php

namespace QUI\ERP\Shipping\Api;

if (!interface_exists(ShippingInterface::class, false)) {
    interface ShippingInterface
    {
        public function getId(): int|string;
    }
}
