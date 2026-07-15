<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

putenv("QUIQQER_OTHER_AUTOLOADERS=KEEP");

require_once __DIR__ . '/../../../../bootstrap.php';

$optionalClassStubs = [
    QUI\ERP\Accounting\Invoice\Invoice::class
        => 'QUI/ERP/Accounting/Invoice/Invoice.php',
    QUI\ERP\Accounting\Invoice\InvoiceTemporary::class
        => 'QUI/ERP/Accounting/Invoice/InvoiceTemporary.php',
    QUI\ERP\Accounting\Invoice\Exception::class
        => 'QUI/ERP/Accounting/Invoice/Exception.php',
    QUI\ERP\Accounting\Invoice\Handler::class
        => 'QUI/ERP/Accounting/Invoice/Handler.php',
    QUI\ERP\Accounting\Invoice\Factory::class
        => 'QUI/ERP/Accounting/Invoice/Factory.php',
    QUI\ERP\Accounting\Invoice\Utils\Invoice::class
        => 'QUI/ERP/Accounting/Invoice/Utils/Invoice.php',
    QUI\ERP\Accounting\Invoice\Articles\Text::class
        => 'QUI/ERP/Accounting/Invoice/Articles/Text.php',
    QUI\ERP\SalesOrders\SalesOrder::class
        => 'QUI/ERP/SalesOrders/SalesOrder.php',
    QUI\ERP\SalesOrders\Handler::class
        => 'QUI/ERP/SalesOrders/Handler.php',
    QUI\ERP\Shipping\Api\ShippingInterface::class
        => 'QUI/ERP/Shipping/Api/ShippingInterface.php',
    QUI\ERP\Shipping\Types\ShippingEntry::class
        => 'QUI/ERP/Shipping/Types/ShippingEntry.php',
    QUI\ERP\Shipping\ShippingStatus\Status::class
        => 'QUI/ERP/Shipping/ShippingStatus/Status.php'
];

foreach ($optionalClassStubs as $className => $stubFile) {
    if (!class_exists($className, false) && !interface_exists($className, false)) {
        require_once __DIR__ . '/phpstan-stubs/' . $stubFile;
    }
}
