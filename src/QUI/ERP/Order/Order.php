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
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     */
    public function __construct($orderId)
    {
        parent::__construct(
            Handler::getInstance()->getOrderData($orderId)
        );
    }

    /**
     * @throws Exception
     * @throws QUI\Exception
     */
    public function refresh()
    {
        $this->setDataBaseData(
            Handler::getInstance()->getOrderData($this->getId())
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
     * @throws QUI\Exception
     */
    public function createInvoice()
    {
        if ($this->isPosted()) {
            return $this->getInvoice();
        }

        $InvoiceFactory   = QUI\ERP\Accounting\Invoice\Factory::getInstance();
        $TemporaryInvoice = $InvoiceFactory->createInvoice(null, $this->getHash());

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            array('temporary_invoice_id' => $TemporaryInvoice->getCleanId()),
            array('id' => $this->getId())
        );


        // set the data to the temporary invoice
        $payment = '';

        $invoiceAddress   = '';
        $invoiceAddressId = '';

        $deliveryAddress   = '';
        $deliveryAddressId = '';

        if ($this->getPayment()) {
            $payment = $this->getPayment()->getId();
        }

        if ($this->getInvoiceAddress()) {
            $invoiceAddress   = $this->getInvoiceAddress()->toJSON();
            $invoiceAddressId = $this->getInvoiceAddress()->getId();
        }

        if ($this->getDeliveryAddress()) {
            $deliveryAddress   = $this->getDeliveryAddress()->toJSON();
            $deliveryAddressId = $this->getDeliveryAddress()->getId();
        }

        $TemporaryInvoice->setAttributes(array(
            'order_id'            => $this->getId(),
            'customer_id'         => $this->customerId,
            'payment_method'      => $payment,
            'invoice_address_id'  => $invoiceAddressId,
            'invoice_address'     => $invoiceAddress,
            'delivery_address'    => $deliveryAddress,
            'delivery_address_id' => $deliveryAddressId
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
        try {
            $TemporaryInvoice->validate();

            // @todo setting -> rechnung automatisch buchen
            $Invoice = $TemporaryInvoice->post();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            throw $Exception;
        }

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            array(
                'temporary_invoice_id' => null,
                'invoice_id'           => $Invoice->getCleanId(),
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
        if (!$this->invoiceId) {
            return false;
        }

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
     * Post the order -> Create an invoice for the order
     * alias for createInvoice()
     *
     * @return QUI\ERP\Accounting\Invoice\Invoice
     *
     * @throws QUI\Exception
     */
    public function post()
    {
        return $this->createInvoice();
    }

    /**
     * Return the order data for saving
     *
     * @return array
     * @throws
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
            $customer = QUI\ERP\Utils\User::filterCustomerAttributes($customer);
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
            'parent_order' => null,
            'invoice_id'   => null,
            'status'       => $this->status,
            'successful'   => $this->successful,

            'customerId'      => $this->customerId,
            'customer'        => json_encode($customer),
            'addressInvoice'  => $InvoiceAddress->toJSON(),
            'addressDelivery' => $deliveryAddress,

            'articles' => $this->Articles->toJSON(),
            'comments' => $this->Comments->toJSON(),
            'history'  => $this->History->toJSON(),
            'data'     => json_encode($this->data),

            'payment_id'      => $paymentId,
            'payment_method'  => $paymentMethod,
            'payment_time'    => null,
            'payment_data'    => '', // verschlüsselt
            'payment_address' => ''  // verschlüsselt
        );
    }

    /**
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @throws QUI\Exception
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
     * Calculates the payment for the order
     *
     * @throws QUI\Exception
     */
    public function calculatePayments()
    {
        $User = QUI::getUserBySession();

        QUI\ERP\Debug::getInstance()->log('Order:: Calculate Payments');

        // old status
        try {
            $oldPaidStatus = $this->getAttribute('paid_status');
            $calculation   = QUI\ERP\Accounting\Calc::calculatePayments($this);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return;
        }

        QUI\ERP\Debug::getInstance()->log('Order:: Calculate -> data');
        QUI\ERP\Debug::getInstance()->log($calculation);

        switch ($calculation['paidStatus']) {
            case self::PAYMENT_STATUS_OPEN:
            case self::PAYMENT_STATUS_PAID:
            case self::PAYMENT_STATUS_PART:
            case self::PAYMENT_STATUS_ERROR:
            case self::PAYMENT_STATUS_DEBIT:
            case self::PAYMENT_STATUS_CANCELED:
                break;

            default:
                $this->setAttribute('paid_status', self::PAYMENT_STATUS_ERROR);
        }

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/order',
                'history.message.edit',
                array(
                    'username' => $User->getName(),
                    'uid'      => $User->getId()
                )
            )
        );

        QUI\ERP\Debug::getInstance()->log('Order:: Calculate -> Update DB');

        if (is_array($calculation['paidData'])) {
            $calculation['paidData'] = json_decode($calculation['paidData']);
        }

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            array(
                'paid_data'   => $calculation['paidData'],
                'paid_date'   => $calculation['paidDate'],
                'paid_status' => $calculation['paidStatus']
            ),
            array('id' => $this->getId())
        );

        QUI\ERP\Debug::getInstance()->log(
            'Order:: Paid Status changed to '.$calculation['paidStatus']
        );


        if ($calculation['paidStatus'] === self::PAYMENT_STATUS_PAID) {
            $this->setSuccessfulStatus();
        }

        // Payment Status has changed
        if ($oldPaidStatus != $this->getAttribute('paid_status')) {
            QUI::getEvents()->fireEvent(
                'onQuiqqerOrderPaymentChanged',
                array($this, $this->getAttribute('paid_status'), $oldPaidStatus)
            );

            QUI::getEvents()->fireEvent(
                'onQuiqqerOrderPaidStatusChanged',
                array($this, $this->getAttribute('paid_status'), $oldPaidStatus)
            );
        }
    }

    /**
     * Delete the order
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional, permission user, default = session user
     * @throws QUI\Exception
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
     *
     * @throws QUI\Exception
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

        $NewOrder->addHistory(
            QUI::getLocale()->get('quiqqer/order', 'message.copy.from', array(
                'orderId' => $this->getId()
            ))
        );

        $NewOrder->update(QUI::getUsers()->getSystemUser());

        return $NewOrder;
    }
}
