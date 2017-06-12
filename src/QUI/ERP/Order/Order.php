<?php


/**
 * This file contains QUI\ERP\Order\Order
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;

/**
 * Class Order
 *
 * @package QUI\ERP\Order
 */
class Order
{
    const STATUS_CREATED = 0;

    const STATUS_POSTED = 1; // Bestellung ist gebucht (Invoice erstellt)

    /**
     * @var bool
     */
    protected $posted = false;

    /**
     * Order constructor.
     *
     * @param string|integer $orderId - Order-ID
     */
    public function __construct($orderId)
    {
        $data = Handler::getInstance()->getOrderData($orderId);

        $this->id         = $data['id'];
        $this->invoice_id = $data['invoice_id'];
        $this->uid        = $data['uid'];
        $this->user       = $data['user'];
        $this->address    = $data['address'];
        $this->products   = $data['products'];
        $this->data       = $data['data'];
        $this->hash       = $data['hash'];
        $this->c_date     = $data['c_date'];
    }

    /**
     * It return the invoice, if an invoice exist for the order
     *
     * @return QUI\ERP\Accounting\Invoice\Invoice
     * @throws QUI\ERP\Accounting\Invoice\Exception
     */
    public function getInvoice()
    {
        return InvoiceHandler::getInstance()->getInvoice($this->id);
    }

    /**
     * Return the order id
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Create an invoice for the order
     */
    public function createInvoice()
    {
        if ($this->isPosted()) {
            throw new Exception(
                array(
                    'quiqqer/order',
                    'exception.message.invoice.for.order.exists'
                ),
                406,
                array(
                    'orderId' => $this->getId()
                )
            );
        }

        // @todo implement
    }

    /**
     * Exists an invoice for the order? is the order already posted?
     *
     * @return bool
     */
    public function isPosted()
    {
        try {
            $this->getInvoice();
        } catch (QUI\ERP\Accounting\Invoice\Exception $Exception) {
            return false;
        }

        return true;
    }
}
