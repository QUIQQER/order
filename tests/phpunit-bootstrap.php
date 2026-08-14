<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

putenv("QUIQQER_OTHER_AUTOLOADERS=KEEP");

if (file_exists(__DIR__ . '/../../../../bootstrap.php')) {
    require_once __DIR__ . '/../../../../bootstrap.php';
}

if (file_exists(__DIR__ . '/../../../autoload.php')) {
    require_once __DIR__ . '/../../../autoload.php';
}

require_once __DIR__ . '/stubs/QUI/ERP/DemoData/DemoDataStubs.php';

$optionalInvoiceStubs = [
    QUI\ERP\Accounting\Invoice\Invoice::class
        => 'QUI/ERP/Accounting/Invoice/Invoice.php',
    QUI\ERP\Accounting\Invoice\InvoiceTemporary::class
        => 'QUI/ERP/Accounting/Invoice/InvoiceTemporary.php',
    QUI\ERP\Accounting\Invoice\Exception::class
        => 'QUI/ERP/Accounting/Invoice/Exception.php',
    QUI\ERP\Accounting\Invoice\Handler::class
        => 'QUI/ERP/Accounting/Invoice/Handler.php'
];

foreach ($optionalInvoiceStubs as $className => $stubFile) {
    if (!class_exists($className)) {
        require_once __DIR__ . '/phpstan-stubs/' . $stubFile;
    }
}

if (!class_exists(QUI\ERP\SalesOrders\SalesOrder::class, false)) {
    require_once __DIR__ . '/phpstan-stubs/QUI/ERP/SalesOrders/SalesOrder.php';
}

$PackageLoader = new Composer\Autoload\ClassLoader();
$PackageLoader->addPsr4('QUI\\ERP\\Order\\', dirname(__DIR__) . '/src/QUI/ERP/Order');
$PackageLoader->addPsr4('QUITests\\ERP\\Order\\', __DIR__ . '/QUITests/ERP/Order');
$PackageLoader->register(true);
