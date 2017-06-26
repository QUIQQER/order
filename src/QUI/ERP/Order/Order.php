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
        return InvoiceHandler::getInstance()->getInvoice($this->invoiceId);
    }

    /**
     * Create an invoice for the order
     *
     * @return QUI\ERP\Accounting\Invoice\Invoice
     *
     * @throws Exception|QUI\ERP\Accounting\Invoice\Exception
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

        $InvoiceFactory   = QUI\ERP\Accounting\Invoice\Factory::getInstance();
        $TemporaryInvoice = $InvoiceFactory->createInvoice();

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            array('temporary_invoice_id' => $TemporaryInvoice->getId()),
            array('id' => $this->getId())
        );


        // set the data to the temporary invoice
        $payment         = '';
        $invoiceAddress  = '';
        $deliveryAddress = '';

        if ($this->getPayment()) {
            $payment = $this->getPayment()->getId();
        }

        if ($this->getInvoiceAddress()) {
            $invoiceAddress = $this->getInvoiceAddress()->toJSON();
        }

        if ($this->getDeliveryAddress()) {
            $invoiceAddress = $this->getDeliveryAddress()->toJSON();
        }

        $TemporaryInvoice->setAttributes(array(
            'order_id'           => $this->getId(),
            'customer_id'        => $this->getCustomer()->getId(),
            'payment_method'     => $payment,
            'invoice_address_id' => '',
            'invoice_address'    => $invoiceAddress,
            'delivery_address'   => $deliveryAddress
        ));

        $articles = $this->getArticles()->getArticles();

        foreach ($articles as $Article) {
            try {
                $TemporaryInvoice->getArticles()->addArticle($Article);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        $TemporaryInvoice->save();


        // create the real invoice
        $TemporaryInvoice->validate();
        $Invoice = $TemporaryInvoice->post();

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            array(
                'temporary_invoice_id' => '',
                'invoice_id'           => $Invoice->getId(),
            ),
            array('id' => $this->getId())
        );

        return $Invoice;
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

    /**
     * Alias for isPosted
     *
     * @return bool
     */
    public function hasInvoice()
    {
        return $this->isPosted();
    }

    /**
     * Return the order data for saving
     *
     * @return array
     */
    protected function getDataForSaving()
    {
        $InvoiceAddress  = $this->getInvoiceAddress();
        $DeliveryAddress = $this->getDeliveryAddress();

        $deliveryAddress = '';
        $customer        = '';

        if ($DeliveryAddress) {
            $deliveryAddress = $DeliveryAddress->toJSON();
        }

        // customer
        try {
            $Customer = $this->getCustomer();
            $customer = $Customer->getAttributes();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        //payment
        $paymentId     = '';
        $paymentMethod = '';

        $Payment = $this->getPayment();

        if ($Payment) {
            $paymentId     = $Payment->getId();
            $paymentMethod = $Payment->getPaymentType()->getTitle();
        }

        return array(
            'parent_order' => '',
            'invoice_id'   => '',
            'status'       => '',

            'customerId'      => $this->customerId,
            'customer'        => json_encode($customer),
            'addressInvoice'  => $InvoiceAddress->toJSON(),
            'addressDelivery' => $deliveryAddress,

            'articles' => $this->Articles->toJSON(),
            'data'     => json_encode($this->data),

            'payment_id'      => $paymentId,
            'payment_method'  => $paymentMethod,
            'payment_time'    => '',
            'payment_data'    => '', // verschlüsselt
            'payment_address' => ''  // verschlüsselt
        );
    }

    /**
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     */
    public function update($PermissionUser = null)
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        QUI\Permissions\Permission::hasPermission(
            'quiqqer.order.update',
            $PermissionUser
        );

        $data = $this->getDataForSaving();

        QUI::getEvents()->fireEvent(
            'quiqqerOrderUpdateBegin',
            array($this, $data)
        );

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            $data,
            array('id' => $this->getId())
        );

        QUI::getEvents()->fireEvent(
            'quiqqerOrderUpdate',
            array($this, $data)
        );
    }

    /**
     * Delete the order
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional, permission user, default = session user
     */
    public function delete($PermissionUser = null)
    {
        QUI\Permissions\Permission::hasPermission(
            'quiqqer.order.delete',
            $PermissionUser
        );

        QUI::getEvents()->fireEvent(
            'quiqqerOrderDeleteBegin',
            array($this)
        );

        QUI::getDataBase()->delete(
            Handler::getInstance()->table(),
            array('id' => $this->getId())
        );

        QUI::getEvents()->fireEvent(
            'quiqqerOrderDelete',
            array($this->getId(), $this->getDataForSaving())
        );
    }

    /**
     * Copy the order and create a new one
     *
     * @return Order
     */
    public function copy()
    {
        $NewOrder = Factory::getInstance()->create();

        QUI::getEvents()->fireEvent(
            'quiqqerOrderCopyBegin',
            array($this)
        );

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            $this->getDataForSaving(),
            array('id' => $NewOrder->getId())
        );

        QUI::getEvents()->fireEvent(
            'quiqqerOrderCopy',
            array($this)
        );

        return $NewOrder;
    }
}
