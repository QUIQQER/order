<?php

namespace QUITests\ERP\Order\Fixtures;

use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Order\Order;

class InvoiceExceptionOrder extends Order
{
    public function __construct()
    {
    }

    public function getInvoice(): Invoice | InvoiceTemporary
    {
        throw new \QUI\Exception('Invoice is unavailable in this test.');
    }
}
