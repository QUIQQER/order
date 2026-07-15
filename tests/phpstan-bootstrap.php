<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

putenv("QUIQQER_OTHER_AUTOLOADERS=KEEP");

require_once __DIR__ . '/../../../../bootstrap.php';

require_once __DIR__ . '/phpstan-stubs/QUI/ERP/Accounting/Invoice/Invoice.php';
require_once __DIR__ . '/phpstan-stubs/QUI/ERP/Accounting/Invoice/InvoiceTemporary.php';
require_once __DIR__ . '/phpstan-stubs/QUI/ERP/Accounting/Invoice/Exception.php';
require_once __DIR__ . '/phpstan-stubs/QUI/ERP/Accounting/Invoice/Handler.php';
require_once __DIR__ . '/phpstan-stubs/QUI/ERP/Accounting/Invoice/Factory.php';
require_once __DIR__ . '/phpstan-stubs/QUI/ERP/Accounting/Invoice/Utils/Invoice.php';
require_once __DIR__ . '/phpstan-stubs/QUI/ERP/Accounting/Invoice/Articles/Text.php';
require_once __DIR__ . '/phpstan-stubs/QUI/ERP/SalesOrders/SalesOrder.php';
require_once __DIR__ . '/phpstan-stubs/QUI/ERP/SalesOrders/Handler.php';
require_once __DIR__ . '/phpstan-stubs/QUI/ERP/Shipping/Api/ShippingInterface.php';
require_once __DIR__ . '/phpstan-stubs/QUI/ERP/Shipping/Types/ShippingEntry.php';
require_once __DIR__ . '/phpstan-stubs/QUI/ERP/Shipping/ShippingStatus/Status.php';
