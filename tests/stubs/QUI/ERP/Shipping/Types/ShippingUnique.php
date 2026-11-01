<?php

namespace QUI\ERP\Shipping\Types;

use QUI\ERP\Shipping\Api\ShippingInterface;

if (!class_exists(ShippingUnique::class, false)) {
    abstract class ShippingUnique implements ShippingInterface
    {
    }
}
