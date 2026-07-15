<?php

namespace QUI\ERP\Shipping\Types;

if (!class_exists(ShippingEntry::class)) {
    abstract class ShippingEntry implements \QUI\ERP\Shipping\Api\ShippingInterface
    {
        abstract public function setErpEntity(\QUI\ERP\ErpEntityInterface $Entity): void;

        /**
         * @return array<string, mixed>
         */
        abstract public function toArray(): array;

        abstract public function toJSON(): string;

        abstract public function getPrice(): \QUI\ERP\Money\Price;
    }
}
