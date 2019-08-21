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
 * - The user has already clicked here on order for a fee
 * - OR this is an order created in the backend
 *
 * @package QUI\ERP\Order
 */
class Order extends AbstractOrder implements OrderInterface
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
     * @return QUI\ERP\Accounting\Invoice\Invoice|QUI\ERP\Accounting\Invoice\InvoiceTemporary
     *
     * @throws QUI\Exception
     * @throws QUI\ERP\Accounting\Invoice\Exception
     */
    public function getInvoice()
    {
        if (!Settings::getInstance()->isInvoiceInstalled()) {
            throw new QUI\Exception([
                'quiqqer/order',
                'exception.invoice.is.not.installed'
            ]);
        }

        try {
            return InvoiceHandler::getInstance()->getInvoice($this->invoiceId);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return InvoiceHandler::getInstance()->getTemporaryInvoice(
            $this->getAttribute('temporary_invoice_id')
        );
    }

    /**
     * Return the view object
     *
     * @return OrderView
     *
     * @throws QUI\Exception
     */
    public function getView()
    {
        return new OrderView($this);
    }

    /**
     * Create an invoice for the order
     *
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return QUI\ERP\Accounting\Invoice\Invoice|QUI\ERP\Accounting\Invoice\InvoiceTemporary
     *
     * @throws QUI\Exception
     */
    public function createInvoice($PermissionUser = null)
    {
        if (Settings::getInstance()->forceCreateInvoice() === false &&
            $this->isPosted()) {
            return $this->getInvoice();
        }

        if (!Settings::getInstance()->isInvoiceInstalled()) {
            throw new QUI\Exception([
                'quiqqer/order',
                'exception.invoice.is.not.installed'
            ]);
        }

        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUsers()->getUserBySession();
        }

        $InvoiceFactory = QUI\ERP\Accounting\Invoice\Factory::getInstance();

        $TemporaryInvoice = $InvoiceFactory->createInvoice(
            QUI::getUserBySession(),
            $this->getHash()
        );

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            ['temporary_invoice_id' => $TemporaryInvoice->getCleanId()],
            ['id' => $this->getId()]
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

        $TemporaryInvoice->setAttributes([
            'order_id'            => $this->getId(),
            'customer_id'         => $this->customerId,
            'payment_method'      => $payment,
            'invoice_address_id'  => $invoiceAddressId,
            'invoice_address'     => $invoiceAddress,
            'delivery_address'    => $deliveryAddress,
            'delivery_address_id' => $deliveryAddressId
        ]);

        // pass data to the invoice
        if (!\is_array($this->data)) {
            $this->data = [];
        }

        foreach ($this->data as $key => $value) {
            $TemporaryInvoice->setData($key, $value);
        }

        $articles = $this->getArticles()->getArticles();

        $TemporaryInvoice->getArticles()->setUser($this->getCustomer());
        $TemporaryInvoice->getArticles()->setCurrency($this->getCurrency());

        foreach ($articles as $Article) {
            $TemporaryInvoice->getArticles()->addArticle($Article);
        }

        $TemporaryInvoice->getArticles()->importPriceFactors(
            $this->getArticles()->getPriceFactors()
        );

        $TemporaryInvoice->save($PermissionUser);

        // save payment data
        QUI::getDataBase()->update(
            InvoiceHandler::getInstance()->temporaryInvoiceTable(),
            [
                'shipping_id'   => $this->shippingId,
                'paid_status'   => $this->getAttribute('paid_status'),
                'payment_data'  => QUI\Security\Encryption::encrypt(\json_encode($this->paymentData)),
                'currency_data' => \json_encode($this->getCurrency()->toArray()),
                'currency'      => $this->getCurrency()->getCode(),
            ],
            ['id' => $this->getId()]
        );

        // create the real invoice
        try {
            $TemporaryInvoice = InvoiceHandler::getInstance()->getTemporaryInvoice(
                $TemporaryInvoice->getId()
            );

            $this->setAttribute('temporary_invoice_id', $TemporaryInvoice->getId());
            $this->save();

            $TemporaryInvoice->validate();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            throw $Exception;
        }

        // auto invoice post
        if (Settings::getInstance()->get('order', 'autoInvoicePost')) {
            $Invoice = $TemporaryInvoice->post();

            QUI::getDataBase()->update(
                Handler::getInstance()->table(),
                ['invoice_id' => $Invoice->getCleanId()],
                ['id' => $this->getId()]
            );

            $this->invoiceId = $Invoice->getId();

            return InvoiceHandler::getInstance()->getInvoice($Invoice->getId());
        }

        return $TemporaryInvoice;
    }

    /**
     * Exists an invoice for the order? is the order already posted?
     *
     * @return bool
     */
    public function isPosted()
    {
        if (!$this->invoiceId && !$this->getAttribute('temporary_invoice_id')) {
            return false;
        }

        if (!$this->invoiceId && $this->getAttribute('temporary_invoice_id')) {
            try {
                InvoiceHandler::getInstance()->getTemporaryInvoice(
                    $this->getAttribute('temporary_invoice_id')
                );

                return true;
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        try {
            $this->getInvoice();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

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
     *
     * @deprecated use createInvoice
     */
    public function post()
    {
        return $this->createInvoice();
    }

    /**
     * Return the order data for saving
     *
     * @return array
     * @throws QUI\Exception
     */
    protected function getDataForSaving()
    {
        $InvoiceAddress  = $this->getInvoiceAddress();
        $DeliveryAddress = $this->getDeliveryAddress();
        $deliveryAddress = '';

        if ($DeliveryAddress) {
            $deliveryAddress = $DeliveryAddress->toJSON();
        }

        if ($this->getShipping()) {
            $ShippingHandler = QUI\ERP\Shipping\Shipping::getInstance();
            $Shipping        = $ShippingHandler->getShippingByObject($this);
            $Address         = $Shipping->getAddress();

            $deliveryAddress = $Address->toJSON();
        }


        // customer
        $Customer = $this->getCustomer();
        $customer = $Customer->getAttributes();
        $customer = QUI\ERP\Utils\User::filterCustomerAttributes($customer);

        //payment
        $idPrefix      = '';
        $paymentId     = null;
        $paymentMethod = null;

        $Payment = $this->getPayment();

        if ($Payment) {
            $paymentId     = $Payment->getId();
            $paymentMethod = $Payment->getPaymentType()->getTitle();
        }

        if ($this->idPrefix !== null) {
            $idPrefix = $this->idPrefix;
        }

        if (!$this->successful && $this->idPrefix === null) {
            $idPrefix = QUI\ERP\Order\Utils\Utils::getOrderPrefix();
        }

        // invoice
        $invoiceId = null;

        if ($this->hasInvoice()) {
            $invoiceId = $this->getInvoice()->getCleanId();
        }

        // currency exchange rate
        $Currency = $this->getCurrency();

        if (QUI\ERP\Defaults::getCurrency()->getCode() !== $Currency->getCode()) {
            $this->setData('currency-exchange-rate', $Currency->getExchangeRate());
        }

        //shipping
        $shippingId   = null;
        $shippingData = '';

        $Shipping = $this->getShipping();

        if ($Shipping) {
            $shippingId   = $Shipping->getId();
            $shippingData = $Shipping->toJSON();
        }


        return [
            'id_prefix'    => $idPrefix,
            'id_str'       => $idPrefix.$this->getId(),
            'parent_order' => null,
            'invoice_id'   => $invoiceId,
            'status'       => $this->status,
            'successful'   => $this->successful,

            'customerId'      => $this->customerId,
            'customer'        => \json_encode($customer),
            'addressInvoice'  => $InvoiceAddress->toJSON(),
            'addressDelivery' => $deliveryAddress,

            'articles'      => $this->Articles->toJSON(),
            'comments'      => $this->Comments->toJSON(),
            'history'       => $this->History->toJSON(),
            'data'          => \json_encode($this->data),
            'currency_data' => \json_encode($this->getCurrency()->toArray()),
            'currency'      => $this->getCurrency()->getCode(),

            'payment_id'      => $paymentId,
            'payment_method'  => $paymentMethod,
            'payment_time'    => null,
            'payment_data'    => QUI\Security\Encryption::encrypt(
                \json_encode($this->paymentData)
            ),
            'payment_address' => '',  // verschlüsselt

            'shipping_id'   => $shippingId,
            'shipping_data' => $shippingData
        ];
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

        if (!QUI::getUsers()->isSystemUser($PermissionUser)
            && $PermissionUser->getId() !== $this->getCustomer()->getId()) {
            QUI\Permissions\Permission::hasPermission(
                'quiqqer.order.update',
                $PermissionUser
            );
        }

        // set status change
        if ($this->statusChanged) {
            $status = $this->status;

            try {
                $Status = QUI\ERP\Order\ProcessingStatus\Handler::getInstance()->getProcessingStatus($status);
                $status = $Status->getTitle();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }

            $this->History->addComment(
                QUI::getLocale()->get(
                    'quiqqer/order',
                    'message.change.order.status',
                    [
                        'status'   => $status,
                        'statusId' => $this->status
                    ]
                )
            );
        }

        // save data
        $data = $this->getDataForSaving();

        QUI::getEvents()->fireEvent(
            'quiqqerOrderUpdateBegin',
            [$this, $data]
        );

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            $data,
            ['id' => $this->getId()]
        );


        if ($this->statusChanged) {
            try {
                QUI::getEvents()->fireEvent('quiqqerOrderProcessStatusChange', [
                    $this,
                    QUI\ERP\Order\ProcessingStatus\Handler::getInstance()->getProcessingStatus($this->status)
                ]);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        QUI::getEvents()->fireEvent('quiqqerOrderUpdate', [$this, $data]);
    }

    /**
     * Alias for update()
     *
     * @param null $PermissionUser
     * @throws QUI\Exception
     */
    public function save($PermissionUser = null)
    {
        $this->update($PermissionUser);
    }

    /**
     * Set Order payment status (paid_status)
     *
     * @param int $status
     * @param bool $force - default = false, if true, set payment status will be set in any case
     *
     * @return void
     * @throws \QUI\Exception
     */
    public function setPaymentStatus(int $status, $force = false)
    {
        $oldPaidStatus = $this->getAttribute('paid_status');

        if ($oldPaidStatus == $status && $force === false) {
            return;
        }

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            ['paid_status' => $status],
            ['id' => $this->getId()]
        );

        QUI\ERP\Debug::getInstance()->log(
            'Order:: Paid Status changed to '.$status
        );

        // Payment Status has changed
        if ($oldPaidStatus != $status) {
            QUI::getEvents()->fireEvent(
                'onQuiqqerOrderPaymentChanged',
                [$this, $status, $oldPaidStatus]
            );

            QUI::getEvents()->fireEvent(
                'onQuiqqerOrderPaidStatusChanged',
                [$this, $status, $oldPaidStatus]
            );
        }

        $this->setAttribute('paid_status', $status);
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
            case self::PAYMENT_STATUS_PLAN:
                break;

            default:
                $this->setAttribute('paid_status', self::PAYMENT_STATUS_ERROR);
        }

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/order',
                'history.message.edit',
                [
                    'username' => $User->getName(),
                    'uid'      => $User->getId()
                ]
            )
        );

        QUI\ERP\Debug::getInstance()->log('Order:: Calculate -> Update DB');

        if (!\is_array($calculation['paidData'])) {
            $calculation['paidData'] = \json_decode($calculation['paidData'], true);
        }

        if (!\is_array($calculation['paidData'])) {
            $calculation['paidData'] = [];
        }

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            [
                'paid_data' => \json_encode($calculation['paidData']),
                'paid_date' => $calculation['paidDate']
            ],
            ['id' => $this->getId()]
        );

        if ($calculation['paidStatus'] === self::PAYMENT_STATUS_PAID) {
            $this->setSuccessfulStatus();

            // create invoice?
            if (Settings::getInstance()->createInvoiceOnPaid()) {
                $this->createInvoice();
            }
        }

        if ($oldPaidStatus !== $calculation['paidStatus']) {
            $this->setPaymentStatus($calculation['paidStatus'], true);
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

        QUI::getEvents()->fireEvent('quiqqerOrderDeleteBegin', [$this]);

        QUI::getDataBase()->delete(
            Handler::getInstance()->table(),
            ['id' => $this->getId()]
        );

        QUI::getEvents()->fireEvent(
            'quiqqerOrderDelete',
            [$this->getId(), $this->getDataForSaving()]
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

        QUI::getEvents()->fireEvent('quiqqerOrderCopyBegin', [$this]);

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            $this->getDataForSaving(),
            ['id' => $NewOrder->getId()]
        );

        QUI::getEvents()->fireEvent('quiqqerOrderCopy', [$this]);

        $NewOrder->addHistory(
            QUI::getLocale()->get('quiqqer/order', 'message.copy.from', [
                'orderId' => $this->getId()
            ])
        );

        $NewOrder->update(QUI::getUsers()->getSystemUser());

        return $NewOrder;
    }

    /**
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional, permission user, default = session user
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\ExceptionStack
     */
    public function clear($PermissionUser = null)
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        if ($PermissionUser->getId() !== $this->getCustomer()->getId()) {
            QUI\Permissions\Permission::hasPermission(
                'quiqqer.order.update',
                $PermissionUser
            );
        }

        QUI::getEvents()->fireEvent('quiqqerOrderClearBegin', [$this]);

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            [
                'articles'   => '[]',
                'status'     => AbstractOrder::STATUS_CREATED,
                'successful' => 0,
                'data'       => '[]',

                'paid_status' => AbstractOrder::PAYMENT_STATUS_OPEN,
                'paid_data'   => null,
                'paid_date'   => null,

                'payment_id'      => null,
                'payment_method'  => null,
                'payment_data'    => null,
                'payment_time'    => null,
                'payment_address' => null
            ],
            ['id' => $this->getId()]
        );

        $this->refresh();

        QUI::getEvents()->fireEvent('quiqqerOrderClear', [$this]);
    }
}
