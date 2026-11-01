<?php

namespace QUI\ERP\Shipping;

use QUI\ERP\ErpEntityInterface;
use QUI\ERP\Shipping\Types\ShippingEntry;
use QUI\ERP\Shipping\Types\ShippingUnique;
use QUI\Utils\Singleton;

if (!class_exists(Shipping::class, false)) {
    abstract class Shipping extends Singleton
    {
        abstract public function getShippingByObject(ErpEntityInterface $Entity): ShippingEntry|ShippingUnique|null;
    }
}
