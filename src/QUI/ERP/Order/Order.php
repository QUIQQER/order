<?php


/**
 * This file contains QUI\ERP\Order\Order
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;

/**
 * Class OrderBooked
 * - This order was ordered by the user
 *
 * @package QUI\ERP\Order
 */
class Order extends AbstractOrder
{
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
        parent::__construct(
            Handler::getInstance()->getOrderData($orderId)
        );
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
