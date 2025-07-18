<?php

/**
 * This file contains QUI\ERP\Order\Order
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\Database\Exception;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\SalesOrders\Handler as SalesOrdersHandler;
use QUI\ERP\SalesOrders\SalesOrder;
use QUI\ERP\ErpEntityInterface as ErpEntityI;
use QUI\ERP\ErpTransactionsInterface as ErpTransactionsI;
use QUI\ERP\ErpCopyInterface as ErpCopyI;
use QUI\ExceptionStack;
use QUI\Interfaces\Users\User;

use function array_map;
use function array_sum;
use function class_exists;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * Class OrderBooked
 * - This order was ordered by the user
 * - The user has already clicked here on order for a fee
 * - OR this is an order created in the backend
 *
 * @package QUI\ERP\Order
 */
class Order extends AbstractOrder implements OrderInterface, ErpEntityI, ErpTransactionsI, ErpCopyI
{
    use QUI\ERP\ErpEntityCustomerFiles;
    use QUI\ERP\ErpEntityData;

    /**
     * @var bool
     */
    protected bool $posted = false;

    /**
     * variable data for developers
     *
     * @var array
     */
    protected mixed $customData = [];

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
        $orderData = Handler::getInstance()->getOrderData($orderId);

        parent::__construct($orderData);

        if (!empty($orderData['custom_data'])) {
            $this->customData = json_decode($orderData['custom_data'], true);
        }
    }

    /**
     * @throws Exception
     * @throws QUI\Exception
     */
    public function refresh(): void
    {
        $this->setDataBaseData(
            Handler::getInstance()->getOrderData($this->getUUID())
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
    public function getInvoice(): QUI\ERP\Accounting\Invoice\Invoice | QUI\ERP\Accounting\Invoice\InvoiceTemporary
    {
        if (!Settings::getInstance()->isInvoiceInstalled()) {
            throw new QUI\Exception([
                'quiqqer/order',
                'exception.invoice.is.not.installed'
            ]);
        }

        if (!empty($this->invoiceId)) {
            try {
                return InvoiceHandler::getInstance()->getInvoice($this->invoiceId);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        try {
            return InvoiceHandler::getInstance()->getTemporaryInvoice(
                $this->getAttribute('temporary_invoice_id')
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        throw new QUI\ERP\Accounting\Invoice\Exception(
            'Order has no invoice',
            404
        );
    }

    /**
     * Return the view object
     *
     * @return OrderView
     */
    public function getView(): OrderView
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
    public function createInvoice(
        null | QUI\Interfaces\Users\User $PermissionUser = null
    ): QUI\ERP\Accounting\Invoice\Invoice | QUI\ERP\Accounting\Invoice\InvoiceTemporary {
        if (Settings::getInstance()->forceCreateInvoice() === false && $this->isPosted()) {
            return $this->getInvoice();
        }

        // nochmal check über die db
        $this->refresh();

        if (Settings::getInstance()->forceCreateInvoice() === false && $this->isPosted()) {
            return $this->getInvoice();
        }

        if (!Settings::getInstance()->isInvoiceInstalled()) {
            throw new QUI\Exception(['quiqqer/order', 'exception.invoice.is.not.installed']);
        }

        // check if order has an invoice address
        // invoice creation is only possible with an address
        $InvoiceAddress = $this->getInvoiceAddress();

        $addressRequired = QUI\ERP\Accounting\Invoice\Utils\Invoice::addressRequirement();
        $addressThreshold = QUI\ERP\Accounting\Invoice\Utils\Invoice::addressRequirementThreshold();
        $Calculation = $this->getPriceCalculation();

        if ($addressRequired === false && $Calculation->getSum()->value() > $addressThreshold) {
            $addressRequired = true;
        }

        $missingAddress = (
            $InvoiceAddress->getName() === ''
            || $InvoiceAddress->getAttribute('street_no') === ''
            || $InvoiceAddress->getAttribute('zip') === ''
            || $InvoiceAddress->getAttribute('city') === ''
            || $InvoiceAddress->getAttribute('country') === ''
        );

        if ($addressRequired && $missingAddress) {
            throw new QUI\Exception(['quiqqer/order', 'exception.missing.address.for.invoice']);
        }

        if (!$this->getPayment()) {
            throw new QUI\Exception(['quiqqer/order', 'exception.to.invoice.missing.payment']);
        }

        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUsers()->getUserBySession();
        }

        $InvoiceFactory = QUI\ERP\Accounting\Invoice\Factory::getInstance();

        $TemporaryInvoice = $InvoiceFactory->createInvoice(
            QUI::getUserBySession(),
            $this->getGlobalProcessId()
        );

        if ($this->getCustomer()) {
            $TemporaryInvoice->setCustomer($this->getCustomer());
        }

        $this->History->addComment(
            QUI::getLocale()->get(
                'quiqqer/order',
                'history.order.invoice.created',
                [
                    'userId' => QUI::getUserBySession()->getUUID(),
                    'username' => QUI::getUserBySession()->getUsername(),
                    'invoiceHash' => $TemporaryInvoice->getUUID()
                ]
            )
        );

        $this->updateHistory();

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            ['temporary_invoice_id' => $TemporaryInvoice->getUUID()],
            ['hash' => $this->getUUID()]
        );


        // set the data to the temporary invoice
        $payment = '';
        $deliveryAddress = '';
        $deliveryAddressId = '';

        if ($this->getPayment()) {
            $payment = $this->getPayment()->getId();
        }

        $invoiceAddress = $this->getInvoiceAddress()->toJSON();
        $invoiceAddressId = $this->getInvoiceAddress()->getUUID();

        if (empty($invoiceAddressId)) {
            $invoiceAddressId = $this->getCustomer()->getStandardAddress()->getUUID();
        }

        if ($this->getDeliveryAddress()->getUUID()) {
            $deliveryAddress = $this->getDeliveryAddress()->toJSON();
            $deliveryAddressId = $this->getDeliveryAddress()->getUUID();

            $TemporaryInvoice->setDeliveryAddress($this->getDeliveryAddress());
        }

        $TemporaryInvoice->setAttributes([
            'order_id' => $this->getUUID(),
            'order_date' => $this->getCreateDate(),
            'project_name' => $this->getAttribute('project_name'),
            'customer_id' => $this->getCustomer()?->getUUID(),
            'customer_data' => $this->getCustomer()?->getAttributes(),
            'payment_method' => $payment,
            'time_for_payment' => QUI\ERP\Customer\Utils::getInstance()->getPaymentTimeForUser($this->customerId),
            'invoice_address_id' => $invoiceAddressId,
            'invoice_address' => $invoiceAddress,
            'delivery_address' => $deliveryAddress,
            'delivery_address_id' => $deliveryAddressId
        ]);

        $TemporaryInvoice->setData('order', $this->getReferenceData());

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
                'customer_id' => $this->getCustomer()?->getUUID(),
                'customer_data' => json_encode($this->getCustomer()?->getAttributes()),
            ],
            ['hash' => $TemporaryInvoice->getUUID()]
        );

        // create the real invoice
        try {
            $TemporaryInvoice = InvoiceHandler::getInstance()->getTemporaryInvoice(
                $TemporaryInvoice->getUUID()
            );

            $this->setAttribute('temporary_invoice_id', $TemporaryInvoice->getUUID());
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
            $Invoice = $TemporaryInvoice->post($PermissionUser);

            QUI::getDataBase()->update(
                Handler::getInstance()->table(),
                ['invoice_id' => $Invoice->getUUID()],
                ['hash' => $this->getUUID()]
            );

            $this->invoiceId = $Invoice->getUUID();

            return InvoiceHandler::getInstance()->getInvoice($Invoice->getUUID());
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

        $SalesOrder = SalesOrdersHandler::createSalesOrder(null, $this->getGlobalProcessId());

        $this->History->addComment(
            QUI::getLocale()->get(
                'quiqqer/order',
                'history.order.salesOrder.created',
                [
                    'userId' => QUI::getUserBySession()->getUUID(),
                    'username' => QUI::getUserBySession()->getUsername(),
                    'salesOrderHash' => $SalesOrder->getUUID()
                ]
            )
        );

        $this->updateHistory();

        // set the data to the temporary invoice
        $payment = '';

        $deliveryAddress = '';
        $deliveryAddressId = '';

        if ($this->getPayment()) {
            $payment = $this->getPayment()->getId();
        }

        $InvoiceAddress = $this->getInvoiceAddress();
        $invoiceAddress = json_decode($InvoiceAddress->toJSON(), true);
        $invoiceAddressId = $InvoiceAddress->getUUID();

        if (empty($invoiceAddressId)) {
            $invoiceAddressId = $this->getCustomer()->getStandardAddress()->getUUID();
        }

        $DeliveryAddress = $this->getDeliveryAddress();

        if ($InvoiceAddress->toJSON() !== $DeliveryAddress->toJSON()) {
            $deliveryAddress = json_decode($DeliveryAddress->toJSON(), true);
            $deliveryAddressId = $DeliveryAddress->getUUID();
        }

        $Customer = $this->getCustomer();
        $ContactPersonAddress = QUI\ERP\Customer\Utils::getInstance()->getContactPersonAddress($Customer);

        $SalesOrder->setAttributes([
            'contact_person' => $ContactPersonAddress ? $ContactPersonAddress->getName() : null,
            'order_date' => $this->getCreateDate(),
            'customer_id' => $Customer->getUUID(),
            'project_name' => $this->getAttribute('project_name'),
            'payment_method' => $payment,
            'customer_address_id' => $invoiceAddressId,
            'customer_address' => $invoiceAddress,
            'delivery_address' => $deliveryAddress,
            'delivery_address_id' => $deliveryAddressId
        ]);

        $SalesOrder->setData('order', $this->getReferenceData());

        // pass data to the sales order
        if (!is_array($this->data)) {
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
                    'id' => $this->getPrefixedNumber(),
                    'user' => $SessionUser->getName(),
                    'userId' => $SessionUser->getUUID()
                ]
            )
        );

        $SalesOrder->setData('orderId', $this->getUUID());

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
     * @return Invoice|InvoiceTemporary
     *
     * @throws QUI\Exception
     * @deprecated use createInvoice
     */
    public function post(): Invoice | InvoiceTemporary
    {
        return $this->createInvoice(QUI::getUsers()->getSystemUser());
    }

    /**
     * Return the order data for saving
     *
     * @return array
     * @throws QUI\Exception
     */
    protected function getDataForSaving(): array
    {
        $InvoiceAddress = $this->getInvoiceAddress();
        $DeliveryAddress = $this->getDeliveryAddress();
        $deliveryAddress = $DeliveryAddress->toJSON();

        if ($this->getShipping() && class_exists('QUI\ERP\Shipping\Shipping')) {
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
            $Invoice = $this->getInvoice();

            if (
                class_exists('QUI\ERP\Accounting\Invoice\Invoice')
                && $Invoice instanceof QUI\ERP\Accounting\Invoice\Invoice
            ) {
                $invoiceId = $Invoice->getUUID();
            }
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

        if (class_exists('QUI\ERP\Shipping\Types\ShippingEntry')) {
            $Shipping = $this->getShipping();

            if ($Shipping) {
                $shippingId = $Shipping->getId();
                $shippingData = $Shipping->toJSON();
            }
        }

        if (class_exists('QUI\ERP\Shipping\ShippingStatus\Status')) {
            $ShippingStatus = $this->getShippingStatus();
            $shippingStatus = $ShippingStatus?->getId();
        }

        // project name
        $projectName = '';

        if (!empty($this->getAttribute('project_name'))) {
            $projectName = $this->getAttribute('project_name');
        }

        return [
            'id_prefix' => $idPrefix,
            'id_str' => $this->getPrefixedNumber(),
            'parent_order' => null,
            'invoice_id' => $invoiceId,
            'status' => $this->status,
            'successful' => $this->successful,
            'c_date' => $this->getCreateDate(),
            'project_name' => $projectName,

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
     * @throws QUI\Exception
     */
    public function update(?User $PermissionUser = null): void
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        if (
            !QUI::getUsers()->isSystemUser($PermissionUser)
            && $PermissionUser->getUUID() !== $this->getCustomer()->getUUID()
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

        $this->History->addComment(
            QUI::getLocale()->get(
                'quiqqer/order',
                'history.order.edit',
                [
                    'userId' => QUI::getUserBySession()->getUUID(),
                    'username' => QUI::getUserBySession()->getUsername()
                ]
            )
        );

        // save data
        $data = $this->getDataForSaving();

        if (
            QUI::isFrontend()
            && $this->isSuccessful()
            && !QUI::getUsers()->isSystemUser($PermissionUser)
        ) {
            // if order is successful
            // only some stuff are allowed to change

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
            ['hash' => $this->getUUID()]
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

        // calc new status if it is needed
        // if price of the articles has been changed
        $paidData = $this->getAttribute('paid_data');
        $paid = 0;
        $newPrice = 0;

        if (isset($data['articles'])) {
            $newPrice = json_decode($data['articles'], true);
            $newPrice = $newPrice['calculations']['sum'];
        }

        if (is_string($paidData)) {
            $paidData = json_decode($paidData, true) ?? [];
        }

        if (is_array($paidData)) {
            $paidData = array_map(function ($paidEntry) {
                return $paidEntry['amount'];
            }, $paidData);

            if (!empty($paidData)) {
                $paid = array_sum($paidData);
            }
        }

        if ($paid !== $newPrice) {
            $this->setAttribute('paid_status', QUI\ERP\Constants::PAYMENT_STATUS_OPEN);
            $this->calculatePayments(false);
        }

        QUI::getEvents()->fireEvent('quiqqerOrderUpdate', [
            $this,
            &$data
        ]);
    }

    /**
     * Alias for update()
     *
     * @throws QUI\Exception
     */
    public function save(?User $PermissionUser = null): void
    {
        $this->update($PermissionUser);
    }

    /**
     * Saves the current history to the order
     * @throws Exception
     */
    public function updateHistory(): void
    {
        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            ['history' => $this->History->toJSON()],
            ['hash' => $this->getUUID()]
        );
    }

    /**
     * Set Order payment status (paid_status)
     *
     * @param int $status
     * @param bool $force - default = false, if true, set payment status will be set in any case
     *
     * @return void
     * @throws QUI\Exception
     */
    public function setPaymentStatus(int $status, bool $force = false): void
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
            ['hash' => $this->getUUID()]
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
     * @param bool $writeHistory - default: true; writes a history entry
     * @throws QUI\Exception
     */
    public function calculatePayments(bool $writeHistory = true): void
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

        if ($writeHistory) {
            $this->addHistory(
                QUI::getLocale()->get(
                    'quiqqer/order',
                    'history.message.edit',
                    [
                        'username' => $User->getName(),
                        'uid' => $User->getUUID()
                    ]
                )
            );
        }

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
            ['hash' => $this->getUUID()]
        );

        if ($calculation['paidStatus'] === QUI\ERP\Constants::PAYMENT_STATUS_PAID) {
            $this->setSuccessfulStatus();

            // create invoice?
            if (Settings::getInstance()->createInvoiceOnPaid()) {
                $this->createInvoice(QUI::getUsers()->getSystemUser());
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
    public function delete($PermissionUser = null): void
    {
        QUI\Permissions\Permission::hasPermission(
            'quiqqer.order.delete',
            $PermissionUser
        );

        QUI::getEvents()->fireEvent('quiqqerOrderDeleteBegin', [$this]);

        QUI::getDataBase()->delete(
            Handler::getInstance()->table(),
            ['hash' => $this->getUUID()]
        );

        QUI::getEvents()->fireEvent(
            'quiqqerOrderDelete',
            [
                $this->getUUID(),
                array_merge(
                    ['hash' => $this->getUUID()],
                    $this->getDataForSaving()
                )
            ]
        );
    }

    /**
     * Copy the order and create a new one
     *
     * @param User|null $PermissionUser
     * @param bool|string $globalProcessId
     * @return Order
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws ExceptionStack
     * @throws Exception
     */
    public function copy(
        null | QUI\Interfaces\Users\User $PermissionUser = null,
        bool | string $globalProcessId = false
    ): Order {
        $NewOrder = Factory::getInstance()->create();

        QUI::getEvents()->fireEvent('quiqqerOrderCopyBegin', [$this]);

        $data = $this->getDataForSaving();

        if (empty($globalProcessId)) {
            unset($data['id_prefix']);
            unset($data['id_str']);
            unset($data['parent_order']);
            unset($data['invoice_id']);
            unset($data['c_date']);
            unset($data['data']);

            $data['history'] = '[]';
            $data['comments'] = '[]';

            $data['global_process_id'] = $NewOrder->getUUID();
        }

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            $data,
            ['hash' => $NewOrder->getUUID()]
        );


        $this->History->addComment(
            QUI::getLocale()->get(
                'quiqqer/order',
                'history.order.copy.created',
                [
                    'userId' => QUI::getUserBySession()->getUUID(),
                    'username' => QUI::getUserBySession()->getUsername(),
                    'orderHash' => $NewOrder->getUUID()
                ]
            )
        );

        $this->updateHistory();


        $NewOrder->addHistory(
            QUI::getLocale()->get('quiqqer/order', 'message.copy.from', [
                'orderId' => $this->getUUID()
            ])
        );

        $NewOrder->updateHistory();


        QUI::getEvents()->fireEvent('quiqqerOrderCopy', [$this]);

        return $NewOrder;
    }

    /**
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional, permission user, default = session user
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\ExceptionStack
     */
    public function clear($PermissionUser = null): void
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        if ($PermissionUser->getUUID() !== $this->getCustomer()->getUUID()) {
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
            ['hash' => $this->getUUID()]
        );

        $this->refresh();

        QUI::getEvents()->fireEvent('quiqqerOrderClear', [$this]);
    }

    /**
     * @return void
     */
    protected function saveFrontendMessages(): void
    {
        try {
            QUI::getDataBase()->update(
                Handler::getInstance()->table(),
                ['frontendMessages' => $this->FrontendMessage->toJSON()],
                ['hash' => $this->getUUID()]
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage(), [
                'order' => $this->getUUID(),
                'orderHash' => $this->getUUID(),
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
    public function setSuccessfulStatus(): void
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

    //region custom data


    /**
     * Add a custom data entry
     *
     * @param string $key
     * @param mixed $value
     *
     * @throws QUI\Database\Exception
     * @throws QUI\Exception
     * @throws QUI\ExceptionStack
     */
    public function addCustomDataEntry(string $key, mixed $value): void
    {
        $this->customData[$key] = $value;

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            ['custom_data' => json_encode($this->customData)],
            ['id' => $this->id]
        );

        QUI::getEvents()->fireEvent(
            'onQuiqqerOrderAddCustomData',
            [$this, $this, $this->customData, $key, $value]
        );
    }

    /**
     * Return a wanted custom data entry
     *
     * @param $key
     * @return mixed|null
     */
    public function getCustomDataEntry($key): mixed
    {
        if (isset($this->customData[$key])) {
            return $this->customData[$key];
        }

        return null;
    }

    /**
     * Return all custom data
     *
     * @return array
     */
    public function getCustomData(): array
    {
        return $this->customData;
    }

    //endregion
}
