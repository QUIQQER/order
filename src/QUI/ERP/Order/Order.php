<?php

/**
 * This file contains QUI\ERP\Order\Order
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\SalesOrders\Handler as SalesOrdersHandler;
use QUI\ERP\SalesOrders\SalesOrder;

use function json_decode;

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
    protected bool $posted = false;

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
     * It returns the invoice, if an invoice exist for the order
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
        if (Settings::getInstance()->forceCreateInvoice() === false && $this->isPosted()) {
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

        $invoiceAddress = '';
        $invoiceAddressId = '';

        $deliveryAddress = '';
        $deliveryAddressId = '';

        if ($this->getPayment()) {
            $payment = $this->getPayment()->getId();
        }

        if ($this->getInvoiceAddress()) {
            $invoiceAddress = $this->getInvoiceAddress()->toJSON();
            $invoiceAddressId = $this->getInvoiceAddress()->getId();
        }

        if (empty($invoiceAddressId)) {
            $invoiceAddressId = $this->getCustomer()->getStandardAddress()->getId();
        }

        if ($this->getDeliveryAddress()) {
            $deliveryAddress = $this->getDeliveryAddress()->toJSON();
            $deliveryAddressId = $this->getDeliveryAddress()->getId();
        }

        $TemporaryInvoice->setAttributes([
            'order_id' => $this->getId(),
            'order_date' => $this->getCreateDate(),
            'customer_id' => $this->customerId,
            'payment_method' => $payment,
            'time_for_payment' => QUI\ERP\Customer\Utils::getInstance()->getPaymentTimeForUser($this->customerId),
            'invoice_address_id' => $invoiceAddressId,
            'invoice_address' => $invoiceAddress,
            'delivery_address' => $deliveryAddress,
            'delivery_address_id' => $deliveryAddressId
        ]);

        // pass data to the invoice
        if (!is_array($this->data)) {
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
                'shipping_id' => $this->shippingId,
                'paid_status' => $this->getAttribute('paid_status'),
                'payment_data' => QUI\Security\Encryption::encrypt(json_encode($this->paymentData)),
                'currency_data' => json_encode($this->getCurrency()->toArray()),
                'currency' => $this->getCurrency()->getCode(),
            ],
            ['id' => $TemporaryInvoice->getCleanId()]
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
            if (QUI::isFrontend()) {
                QUI\System\Log::writeException($Exception);
            }

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
     * Create a sales order from this order.
     *
     * @return SalesOrder
     * @throws QUI\Exception
     */
    public function createSalesOrder(): SalesOrder
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/salesorders')) {
            throw new QUI\Exception([
                'quiqqer/order',
                'exception.createSalesOrder.package_not_installed'
            ]);
        }

        $SalesOrder = SalesOrdersHandler::createSalesOrder(null, $this->getHash());

        // set the data to the temporary invoice
        $payment = '';

        $deliveryAddress = '';
        $deliveryAddressId = '';

        if ($this->getPayment()) {
            $payment = $this->getPayment()->getId();
        }

        $InvoiceAddress = $this->getInvoiceAddress();
        $invoiceAddress = json_decode($InvoiceAddress->toJSON(), true);
        $invoiceAddressId = $InvoiceAddress->getId();

        if (empty($invoiceAddressId)) {
            $invoiceAddressId = $this->getCustomer()->getStandardAddress()->getId();
        }

        $DeliveryAddress = $this->getDeliveryAddress();

        if ($InvoiceAddress->toJSON() !== $DeliveryAddress->toJSON()) {
            $deliveryAddress = json_decode($DeliveryAddress->toJSON(), true);
            $deliveryAddressId = $DeliveryAddress->getId();
        }

        $Customer = $this->getCustomer();
        $ContactPersonAddress = QUI\ERP\Customer\Utils::getInstance()->getContactPersonAddress($Customer);

        $SalesOrder->setAttributes([
            'contact_person' => $ContactPersonAddress ? $ContactPersonAddress->getName() : null,
            'order_date' => $this->getCreateDate(),
            'customer_id' => $Customer->getId(),
            'payment_method' => $payment,
            'customer_address_id' => $invoiceAddressId,
            'customer_address' => $invoiceAddress,
            'delivery_address' => $deliveryAddress,
            'delivery_address_id' => $deliveryAddressId
        ]);

        // pass data to the sales order
        if (!\is_array($this->data)) {
            $this->data = [];
        }

        foreach ($this->data as $key => $value) {
            $SalesOrder->setData($key, $value);
        }

        // Set articles
        $articles = $this->getArticles()->getArticles();

        $SalesOrder->getArticles()->setUser($this->getCustomer());
        $SalesOrder->getArticles()->setCurrency($this->getCurrency());

        foreach ($articles as $Article) {
            $SalesOrder->getArticles()->addArticle($Article);
        }

        $SalesOrder->getArticles()->importPriceFactors(
            $this->getArticles()->getPriceFactors()
        );

        $SessionUser = QUI::getUserBySession();

        $SalesOrder->addHistory(
            QUI::getLocale()->get(
                'quiqqer/order',
                'history.SalesOrder.created_from_order',
                [
                    'id' => $this->getPrefixedId(),
                    'user' => $SessionUser->getName(),
                    'userId' => $SessionUser->getId()
                ]
            )
        );

        $SalesOrder->setData('orderId', $this->getCleanId());

        $SalesOrder->update();

        return $SalesOrder;
    }

    /**
     * Exists an invoice for the order? is the order already posted?
     *
     * @return bool
     */
    public function isPosted(): bool
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
    public function hasInvoice(): bool
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
        $InvoiceAddress = $this->getInvoiceAddress();
        $DeliveryAddress = $this->getDeliveryAddress();
        $deliveryAddress = '';

        if ($DeliveryAddress) {
            $deliveryAddress = $DeliveryAddress->toJSON();
        }

        if ($this->getShipping()) {
            $ShippingHandler = QUI\ERP\Shipping\Shipping::getInstance();
            $Shipping = $ShippingHandler->getShippingByObject($this);
            $Address = $Shipping->getAddress();

            $deliveryAddress = $Address->toJSON();
        }


        // customer
        $Customer = $this->getCustomer();
        $customer = $Customer->getAttributes();
        $customer = QUI\ERP\Utils\User::filterCustomerAttributes($customer);

        //payment
        $idPrefix = '';
        $paymentId = null;
        $paymentMethod = null;

        $Payment = $this->getPayment();

        if ($Payment) {
            $paymentId = $Payment->getId();
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
        $shippingId = null;
        $shippingData = '';
        $shippingStatus = null;

        $Shipping = $this->getShipping();

        if ($Shipping) {
            $shippingId = $Shipping->getId();
            $shippingData = $Shipping->toJSON();
        }

        if (QUI::getPackageManager()->isInstalled('quiqqer/shipping')) {
            $ShippingStatus = $this->getShippingStatus();
            $shippingStatus = $ShippingStatus ? $ShippingStatus->getId() : null;
        }

        return [
            'id_prefix' => $idPrefix,
            'id_str' => $idPrefix . $this->getId(),
            'parent_order' => null,
            'invoice_id' => $invoiceId,
            'status' => $this->status,
            'successful' => $this->successful,
            'c_date' => $this->getCreateDate(),

            'customerId' => $this->customerId,
            'customer' => json_encode($customer),
            'addressInvoice' => $InvoiceAddress->toJSON(),
            'addressDelivery' => $deliveryAddress,

            'articles' => $this->Articles->toJSON(),
            'comments' => $this->Comments->toJSON(),
            'status_mails' => $this->StatusMails->toJSON(),
            'history' => $this->History->toJSON(),
            'frontendMessages' => $this->FrontendMessage->toJSON(),
            'data' => json_encode($this->data),
            'currency_data' => json_encode($this->getCurrency()->toArray()),
            'currency' => $this->getCurrency()->getCode(),

            'payment_id' => $paymentId,
            'payment_method' => $paymentMethod,
            'payment_time' => null,
            'payment_data' => QUI\Security\Encryption::encrypt(
                json_encode($this->paymentData)
            ),
            'payment_address' => '',
            // verschlüsselt

            'shipping_id' => $shippingId,
            'shipping_data' => $shippingData,
            'shipping_status' => $shippingStatus
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

        if (
            !QUI::getUsers()->isSystemUser($PermissionUser)
            && $PermissionUser->getId() !== $this->getCustomer()->getId()
        ) {
            QUI\Permissions\Permission::hasPermission(
                'quiqqer.order.update',
                $PermissionUser
            );
        }


        // set status change
        $fireStatusChangedEvent = false;

        if ($this->statusChanged) {
            $fireStatusChangedEvent = true;
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
                        'status' => $status,
                        'statusId' => $this->status
                    ]
                )
            );

            $this->statusChanged = false;
        }

        // save data
        $data = $this->getDataForSaving();

        if (
            QUI::isFrontend()
            && $this->isSuccessful()
            && !QUI::getUsers()->isSystemUser($PermissionUser)
        ) {
            // if order is successful
            // only some stuff are allowed to changed

            $_data = [
                'payment_id' => $data['payment_id'],
                'payment_method' => $data['payment_method'],
                'payment_data' => $data['payment_data'],
                'payment_address' => $data['payment_address'],
                'comments' => $data['comments'],
                'history' => $data['history'],
                'frontendMessages' => $data['frontendMessages'],
                'successful' => $this->successful
            ];

            $data = $_data;
        }

        QUI::getEvents()->fireEvent(
            'quiqqerOrderUpdateBegin',
            [
                $this,
                &$data
            ]
        );

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            $data,
            ['id' => $this->getId()]
        );


        if ($fireStatusChangedEvent) {
            try {
                QUI::getEvents()->fireEvent('quiqqerOrderProcessStatusChange', [
                    $this,
                    QUI\ERP\Order\ProcessingStatus\Handler::getInstance()->getProcessingStatus($this->status)
                ]);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        QUI::getEvents()->fireEvent('quiqqerOrderUpdate', [
            $this,
            &$data
        ]);
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

        if ($this->getAttribute('old_paid_status') !== false) {
            $oldPaidStatus = $this->getAttribute('old_paid_status');
        }

        if ($oldPaidStatus == $status && $force === false) {
            return;
        }

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            ['paid_status' => $status],
            ['id' => $this->getId()]
        );

        QUI\ERP\Debug::getInstance()->log(
            'Order:: Paid Status changed to ' . $status
        );

        // Payment Status has changed
        if ($oldPaidStatus != $status) {
            QUI::getEvents()->fireEvent(
                'onQuiqqerOrderPaymentChanged',
                [
                    $this,
                    $status,
                    $oldPaidStatus
                ]
            );

            QUI::getEvents()->fireEvent(
                'onQuiqqerOrderPaidStatusChanged',
                [
                    $this,
                    $status,
                    $oldPaidStatus
                ]
            );

            if ($this->isApproved()) {
                $this->triggerApprovalEvent();
            }
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
            $calculation = QUI\ERP\Accounting\Calc::calculatePayments($this);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return;
        }

        QUI\ERP\Debug::getInstance()->log('Order:: Calculate -> data');
        QUI\ERP\Debug::getInstance()->log($calculation);

        switch ($calculation['paidStatus']) {
            case QUI\ERP\Constants::PAYMENT_STATUS_OPEN:
            case QUI\ERP\Constants::PAYMENT_STATUS_PAID:
            case QUI\ERP\Constants::PAYMENT_STATUS_PART:
            case QUI\ERP\Constants::PAYMENT_STATUS_ERROR:
            case QUI\ERP\Constants::PAYMENT_STATUS_DEBIT:
            case QUI\ERP\Constants::PAYMENT_STATUS_CANCELED:
            case QUI\ERP\Constants::PAYMENT_STATUS_PLAN:
                break;

            default:
                $this->setAttribute('paid_status', QUI\ERP\Constants::PAYMENT_STATUS_ERROR);
        }

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/order',
                'history.message.edit',
                [
                    'username' => $User->getName(),
                    'uid' => $User->getId()
                ]
            )
        );

        QUI\ERP\Debug::getInstance()->log('Order:: Calculate -> Update DB');

        if (!is_array($calculation['paidData'])) {
            $calculation['paidData'] = json_decode($calculation['paidData'], true);
        }

        if (!is_array($calculation['paidData'])) {
            $calculation['paidData'] = [];
        }

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            [
                'paid_data' => json_encode($calculation['paidData']),
                'paid_date' => $calculation['paidDate']
            ],
            ['id' => $this->getId()]
        );

        if ($calculation['paidStatus'] === QUI\ERP\Constants::PAYMENT_STATUS_PAID) {
            $this->setSuccessfulStatus();

            // create invoice?
            if (Settings::getInstance()->createInvoiceOnPaid()) {
                $this->createInvoice();
            }
        }

        if ($oldPaidStatus !== $calculation['paidStatus']) {
            $this->setAttribute('old_paid_status', $oldPaidStatus);
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
            [
                $this->getId(),
                array_merge(
                    [
                        'hash' => $this->getHash()
                    ],
                    $this->getDataForSaving()
                )
            ]
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
                'articles' => '[]',
                'status' => QUI\ERP\Constants::ORDER_STATUS_CREATED,
                'successful' => 0,
                'data' => '[]',

                'paid_status' => QUI\ERP\Constants::PAYMENT_STATUS_OPEN,
                'paid_data' => null,
                'paid_date' => null,

                'payment_id' => null,
                'payment_method' => null,
                'payment_data' => null,
                'payment_time' => null,
                'payment_address' => null
            ],
            ['id' => $this->getId()]
        );

        $this->refresh();

        QUI::getEvents()->fireEvent('quiqqerOrderClear', [$this]);
    }

    /**
     * @return mixed|void
     */
    protected function saveFrontendMessages()
    {
        try {
            QUI::getDataBase()->update(
                Handler::getInstance()->table(),
                ['frontendMessages' => $this->FrontendMessage->toJSON()],
                ['id' => $this->getId()]
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage(), [
                'order' => $this->getId(),
                'orderHash' => $this->getHash(),
                'orderType' => $this->getType(),
                'action' => 'Order->clearFrontendMessages'
            ]);
        }
    }

    /**
     * Set the successful status to the order
     * is overwritten here, because the order in process checks if there is an order.
     * if so, do not fire the event quiqqerOrderSuccessful twice, the order already fires this
     *
     * @throws QUI\Exception
     * @throws QUI\ExceptionStack
     */
    public function setSuccessfulStatus()
    {
        $currentStatus = $this->successful;

        parent::setSuccessfulStatus();

        if (!$currentStatus) {
            try {
                QUI::getEvents()->fireEvent('quiqqerOrderSuccessfulCreated', [$this]);
            } catch (\Exception $Exception) {
                QUI\System\Log::addError($Exception->getMessage());
            }
        }
    }
}
