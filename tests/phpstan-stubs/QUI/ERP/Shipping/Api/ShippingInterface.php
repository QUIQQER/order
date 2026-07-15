<?php

namespace QUI\ERP\Shipping\Api;

if (!interface_exists(ShippingInterface::class)) {
    interface ShippingInterface
    {
        public function getId(): int|string;

        public function setErpEntity(\QUI\ERP\ErpEntityInterface $Entity): void;

        public function canUsedInErpEntity(\QUI\ERP\ErpEntityInterface $Entity): bool;
    }
}
