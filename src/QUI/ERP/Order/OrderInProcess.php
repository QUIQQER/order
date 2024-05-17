<?php

/**
 * This file contains QUI\ERP\Order\OrderInProcess
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\Exception;
use QUI\Interfaces\Users\User;
use QUI\ERP\ErpEntityInterface;
use QUI\ERP\ErpTransactionsInterface;

use function defined;
use function is_array;
use function json_decode;
use function json_encode;

/**
 * Class OrderInProcess
 *
 * this is an order in process and not the ordering process
 * This order is currently being processed by the user
 *
 * @package QUI\ERP\Order
 */
class OrderInProcess extends AbstractOrder implements OrderInterface, ErpEntityInterface, ErpTransactionsInterface
{
    protected null|string|int $orderId = null;

    /**
     * Order constructor.
     *
     * @param string|integer $orderId - Order-ID
     *
     * @throws QUI\Erp\Order\Exception
     * @throws QUI\ERP\Exception
     * @throws QUI\Database\Exception
     */
    public function __construct($orderId)
    {
        $data = Handler::getInstance()->getOrderProcessData($orderId);

        parent::__construct($data);

        $this->orderId = $data['order_id'];

        // check if an order for the processing order exists
        try {
            Handler::getInstance()->get($this->orderId);
        } catch (Exception) {
            $this->orderId = null;
        }
    }

    /**
     * Refresh the data, makes a database call
     *
     * @throws Exception
     * @throws QUI\ERP\Exception
     * @throws QUI\Database\Exception
     */
    public function refresh(): void
    {
        if ($this->orderId) {
            if ($this->isSuccessful()) {
                return;
            }

            try {
                Handler::getInstance()->removeFromInstanceCache($this->orderId);
                $Order = Handler::getInstance()->get($this->orderId);

                if (!$Order->isSuccessful()) {
                    $Order->refresh();
                }
            } catch (Exception) {
            }
        }

        $data = Handler::getInstance()->getOrderProcessData($this->getUUID());

        // update customer data
        if (isset($data['customer'])) {
            try {
                $customer = json_decode($data['customer'], true);
                $User = QUI::getUsers()->get($customer['uuid'] ?? $customer['id']);

                $customer['lang'] = $User->getLang();
                $data['customer'] = json_encode($customer);
            } catch (\Exception) {
            }
        }

        $this->setDataBaseData($data);
    }

    public function getOrderId(): null|int|string
    {
        return $this->orderId;
    }

    /**
     * Return the real order id for the customer
     * For the customer this method returns the hash, so he has an association to the real order
     *
     * @return string
     */
    public function getPrefixedId(): string
    {
        if ($this->orderId) {
            try {
                $Order = Handler::getInstance()->get($this->orderId);

                return $Order->getUUID();
            } catch (Exception) {
            }
        }

        return $this->getUUID();
    }

    /**
     * Alias for update
     *
     * @param User|null $PermissionUser
     *
     * @throws Exception
     * @throws QUI\Permissions\Exception
     */
    public function save(QUI\Interfaces\Users\User $PermissionUser = null): void
    {
        $this->update($PermissionUser);
    }

    /**
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     *
     * @throws QUI\Permissions\Exception
     * @throws Exception
     */
    public function update(QUI\Interfaces\Users\User $PermissionUser = null): void
    {
        if ($this->hasPermissions($PermissionUser) === false) {
            throw new QUI\Permissions\Exception(
                QUI::getLocale()->get('quiqqer/system', 'exception.no.permission'),
                403
            );
        }

        if ($this->orderId) {
            $Order = Handler::getInstance()->get($this->orderId);
            $Order->update($PermissionUser);

            return;
        }

        $data = $this->getDataForSaving();

        QUI::getEvents()->fireEvent(
            'quiqqerOrderProcessUpdateBegin',
            [$this, &$data]
        );

        // set status change
        if ($this->statusChanged) {
            $status = $this->status;

            try {
                $Status = QUI\ERP\Order\ProcessingStatus\Handler::getInstance()->getProcessingStatus($status);
                $status = $Status->getTitle();
            } catch (Exception $Exception) {
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
        }


        QUI::getDataBase()->update(
            Handler::getInstance()->tableOrderProcess(),
            $data,
            ['hash' => $this->getUUID()]
        );

        if ($this->statusChanged) {
            try {
                QUI::getEvents()->fireEvent('quiqqerOrderProcessStatusChange', [
                    $this,
                    QUI\ERP\Order\ProcessingStatus\Handler::getInstance()->getProcessingStatus($this->status)
                ]);
            } catch (Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        QUI::getEvents()->fireEvent(
            'quiqqerOrderProcessUpdate',
            [$this, $data]
        );
    }

    /**
     * Add price factors to the order
     *
     * @param array $priceFactors
     *
     * @throws Exception
     * @throws Exception
     */
    public function addPriceFactors(array $priceFactors = []): void
    {
        if ($this->orderId) {
            throw new Exception(
                ['quiqqer/order', 'exception.order.already.exists'],
                403
            );
        }

        $Basket = new QUI\ERP\Order\Basket\BasketOrder(
            $this->getUUID(),
            $this->getCustomer()
        );

        $Products = $Basket->getProducts();

        $ArticleList = new QUI\ERP\Accounting\ArticleList();
        $ArticleList->setUser($this->getCustomer());

        $ProductCalc = QUI\ERP\Products\Utils\Calc::getInstance();
        $ProductCalc->setUser($this->getCustomer());
        $Products->calc($ProductCalc);

        $products = $Products->getProducts();

        foreach ($products as $Product) {
            try {
                /* @var QUI\ERP\Order\Basket\Product $Product */
                $ArticleList->addArticle($Product->toArticle(null, false));
            } catch (Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        foreach ($priceFactors as $PriceFactor) {
            if (!($PriceFactor instanceof QUI\ERP\Products\Interfaces\PriceFactorInterface)) {
                continue;
            }

            $Products->getPriceFactors()->addToEnd($PriceFactor);
        }

        $Products->recalculation();

        // recalculate price factors
        $ArticleList->importPriceFactors(
            $Products->getPriceFactors()->toErpPriceFactorList()
        );

        $ArticleList->calc();

        $this->Articles = $ArticleList;
        $this->update();
    }

    /**
     * Calculates the payment for the order
     *
     * @throws Exception
     * @throws Exception
     * @throws QUI\ERP\Exception
     * @throws QUI\Permissions\Exception
     */
    public function calculatePayments(): void
    {
        QUI\ERP\Debug::getInstance()->log('OrderInProcess:: Calculate Payments');

        $User = QUI::getUserBySession();

        // old status
        $oldPaidStatus = $this->getAttribute('paid_status');
        $calculations = QUI\ERP\Accounting\Calc::calculatePayments($this);

        switch ($this->getAttribute('paid_status')) {
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
                    'uid' => $User->getUUID()
                ]
            )
        );

        if (is_array($calculations['paidData'])) {
            $calculations['paidData'] = json_encode($calculations['paidData']);
        }

        QUI::getDataBase()->update(
            Handler::getInstance()->tableOrderProcess(),
            [
                'paid_data' => $calculations['paidData'],
                'paid_date' => $calculations['paidDate']
            ],
            ['hash' => $this->getUUID()]
        );

        // create order, if the payment status is paid and no order exists
        if ($this->getAttribute('paid_status') === QUI\ERP\Constants::PAYMENT_STATUS_PAID && !$this->orderId) {
            $this->createOrder();
        }

        if ($oldPaidStatus !== $calculations['paidStatus']) {
            $this->setPaymentStatus($calculations['paidStatus'], true);
        }
    }

    /**
     * Delete the processing order
     * The user itself or a super can delete it
     *
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Database\Exception
     */
    public function delete(QUI\Interfaces\Users\User $PermissionUser = null): void
    {
        if ($this->hasPermissions($PermissionUser) === false) {
            throw new QUI\Permissions\Exception(
                QUI::getLocale()->get('quiqqer/system', 'exception.no.permission'),
                403
            );
        }

        QUI::getDataBase()->delete(
            Handler::getInstance()->tableOrderProcess(),
            ['hash' => $this->getUUID()]
        );
    }

    /**
     * An order in process is never finished
     *
     * @return bool
     */
    public function isPosted(): bool
    {
        if ($this->orderId) {
            try {
                $Order = Handler::getInstance()->get($this->getOrderId());

                return $Order->isPosted();
            } catch (Exception) {
                return false;
            }
        }

        return false;
    }

    /**
     * Create the order
     *
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return Order
     *
     * @throws QUI\Permissions\Exception
     * @throws Exception
     * @throws Exception
     */
    public function createOrder(QUI\Interfaces\Users\User $PermissionUser = null): Order
    {
        QUI\ERP\Debug::getInstance()->log('OrderInProcess:: Create Order');

        if ($this->hasPermissions($PermissionUser) === false) {
            throw new QUI\Permissions\Exception(
                QUI::getLocale()->get('quiqqer/core', 'exception.no.permission'),
                403
            );
        }

        // no duplicate is allowed
        if ($this->orderId) {
            return Handler::getInstance()->get($this->orderId);
        }

        // no duplicate is allowed
        try {
            $Order = Handler::getInstance()->getOrderByHash($this->getUUID());

            if ($Order instanceof Order) {
                QUI\System\Log::addInfo('Order->createOrder is already executed ' . $Order->getUUID());

                return $Order;
            }
        } catch (Exception) {
        }


        $SystemUser = QUI::getUsers()->getSystemUser();
        $this->recalculate();

        $Order = Factory::getInstance()->create($SystemUser, $this->getUUID());

        // bind the new order to the order in process
        QUI::getDataBase()->update(
            Handler::getInstance()->tableOrderProcess(),
            ['order_id' => $Order->getUUID()],
            ['hash' => $this->getUUID()]
        );

        $this->orderId = $Order->getUUID();

        // copy the data to the order
        $data = $this->getDataForSaving();
        $data['id_prefix'] = $Order->getIdPrefix();
        $data['id_str'] = $Order->getPrefixedNumber();
        $data['order_process_id'] = $this->getUUID();
        $data['global_process_id'] = $this->getGlobalProcessId();
        $data['c_user'] = $this->cUser;
        $data['paid_status'] = $this->getAttribute('paid_status');
        $data['paid_date'] = $this->getAttribute('paid_date');
        $data['paid_data'] = $this->getAttribute('paid_data');
        $data['successful'] = $this->successful;

        if (empty($data['paid_date'])) {
            $data['paid_date'] = null;
        }

        if (is_array($data['paid_data'])) {
            $data['paid_data'] = json_encode($data['paid_data']);
        }

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            $data,
            ['hash' => $Order->getUUID()]
        );

        $Order->setAttribute('inOrderCreation', true);
        $this->setAttribute('inOrderCreation', true);

        // get the order with new data
        $Order->setCurrency($this->getCurrency());
        $Order->refresh();
        $Order->recalculate();

        QUI\ERP\Debug::getInstance()->log('OrderInProcess:: Order created');
        QUI\ERP\Debug::getInstance()->log('OrderInProcess:: Order calculatePayments');

        try {
            // reset & recalculation
            $Order->setAttribute('paid_status', QUI\ERP\Constants::PAYMENT_STATUS_OPEN);
            $Order->calculatePayments();

            if ($data['paid_status'] !== $Order->getAttribute('paid_status')) {
                $Order->save();
            }
        } catch (Exception $Exception) {
            if (defined('QUIQQER_DEBUG')) {
                QUI\System\Log::writeException($Exception);
            }
        }

        $Payment = $Order->getPayment();

        if ($Payment && $Payment->isSuccessful($Order->getUUID())) {
            $Order->setSuccessfulStatus();
            $this->setSuccessfulStatus();
        }

        try {
            QUI::getEvents()->fireEvent('quiqqerOrderCreated', [$Order]);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());
        }

        if ($Order->isSuccessful()) {
            try {
                QUI::getEvents()->fireEvent('quiqqerOrderSuccessfulCreated', [$Order]);
            } catch (\Exception $Exception) {
                QUI\System\Log::addError($Exception->getMessage());
            }
        }

        // set accounting currency, if it needed
        if (QUI\ERP\Currency\Conf::accountingCurrencyEnabled()) {
            $AccountingCurrency = QUI\ERP\Currency\Conf::getAccountingCurrency();

            $acData = [
                'accountingCurrency' => $AccountingCurrency->toArray(),
                'currency' => $this->Currency->toArray(),
                'rate' => $this->Currency->getExchangeRate($AccountingCurrency)
            ];

            $Order->setData('accountingCurrencyData', $acData);
            $Order->save();
        }

        $this->delete();

        // create invoice?
        /**
         * The special attribute 'no_invoice_auto_create' was added to allow
         * plugins (e.g. via events) to prevent an Order from creating any invoices
         * so, they may create them on their own. This is used by e.g. quiqqer/contracts.
         *
         * @author Patrick Müller [26.02.2018]
         */
        if ($this->getAttribute('no_invoice_auto_create') || $Order->getAttribute('no_invoice_auto_create')) {
            return $Order;
        }

        if (Settings::getInstance()->createInvoiceOnOrder()) {
            $Order->createInvoice(QUI::getUsers()->getSystemUser());

            return $Order;
        }

        if (
            Settings::getInstance()->createInvoiceByPayment()
            && $Order->getPayment()->isSuccessful($Order->getUUID())
        ) {
            $Order->createInvoice(QUI::getUsers()->getSystemUser());

            return $Order;
        }

        if (
            Settings::getInstance()->createInvoiceByPayment()
            && $Order->getPayment()->isSuccessful($Order->getUUID())
        ) {
            $Order->createInvoice(QUI::getUsers()->getSystemUser());
        }

        return $Order;
    }

    /**
     * Has the user permissions to do things
     *
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return bool
     */
    protected function hasPermissions(QUI\Interfaces\Users\User $PermissionUser = null): bool
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        if ($this->cUser === $PermissionUser->getUUID()) {
            return true;
        }

        if (QUI::getUsers()->isSystemUser($PermissionUser)) {
            return true;
        }

        if ($PermissionUser->isSU()) {
            return true;
        }

        //@todo permissions prüfen

        return false;
    }

    /**
     * Return the order data for saving
     *
     * @return array
     */
    protected function getDataForSaving(): array
    {
        $InvoiceAddress = $this->getInvoiceAddress();
        $DeliveryAddress = $this->getDeliveryAddress();
        $deliveryAddress = $DeliveryAddress->toJSON();

        // customer
        $Customer = $this->getCustomer();
        $customer = $Customer->getAttributes();
        $customer = QUI\ERP\Utils\User::filterCustomerAttributes($customer);

        if (!$InvoiceAddress->getUUID()) {
            $InvoiceAddress = $Customer->getStandardAddress();
        }

        // status
        $status = 0;

        if ($this->status) {
            $status = $this->status;
        }

        //payment
        $paymentId = null;
        $paymentMethod = null;

        $Payment = $this->getPayment();

        try {
            if ($Payment) {
                $paymentId = $Payment->getId();
                $paymentMethod = $Payment->getPaymentType()->getTitle();
            }
        } catch (Exception) {
        }

        //shipping
        $shippingId = null;
        $shippingData = '';
        $shippingStatus = null;

        $Shipping = $this->getShipping();

        if ($Shipping) {
            $shippingId = $Shipping->getId();
            $shippingData = $Shipping->toArray();
        }

        if (QUI::getPackageManager()->isInstalled('quiqqer/shipping')) {
            $ShippingStatus = $this->getShippingStatus();
            $shippingStatus = $ShippingStatus ? $ShippingStatus->getId() : null;
        }

        return [
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
            'status' => $status,
            'successful' => $this->successful,

            'payment_id' => $paymentId,
            'payment_method' => $paymentMethod,
            'payment_time' => null,
            'payment_data' => QUI\Security\Encryption::encrypt(
                json_encode($this->paymentData)
            ), // encrypted
            'payment_address' => '',  // encrypted

            'shipping_id' => $shippingId,
            'shipping_data' => json_encode($shippingData),
            'shipping_status' => $shippingStatus
        ];
    }

    /**
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional, permission user, default = session user
     *
     * @throws Exception
     * @throws Exception
     * @throws QUI\ExceptionStack
     */
    public function clear($PermissionUser = null): void
    {
        if ($this->orderId) {
            $Order = Handler::getInstance()->get($this->getOrderId());
            $Order->clear($PermissionUser);

            return;
        }

        if ($this->hasPermissions($PermissionUser) === false) {
            throw new QUI\Permissions\Exception(
                QUI::getLocale()->get('quiqqer/system', 'exception.no.permission'),
                403
            );
        }

        QUI::getEvents()->fireEvent('quiqqerOrderClearBegin', [$this]);

        $this->delete();

        $hash = $this->getUUID();
        $oldOrderId = $this->getUUID();
        $newOrderId = QUI\ERP\Order\Factory::getInstance()->createOrderInProcessDataBaseEntry();

        QUI::getDataBase()->update(
            Handler::getInstance()->tableOrderProcess(),
            [
                'hash' => $hash,
                'id' => $oldOrderId
            ],
            ['id' => $newOrderId]
        );

        $this->refresh();

        QUI::getEvents()->fireEvent('quiqqerOrderClear', [$this]);
    }

    /**
     * Has the order an invoice?
     *
     * @return bool
     */
    public function hasInvoice(): bool
    {
        if ($this->orderId) {
            try {
                $Order = Handler::getInstance()->get($this->getOrderId());

                return $Order->hasInvoice();
            } catch (Exception) {
                return false;
            }
        }

        return false;
    }

    /**
     * Return the invoice if an invoice exists for the order
     *
     * @return QUI\ERP\Accounting\Invoice\Invoice|QUI\ERP\Accounting\Invoice\InvoiceTemporary
     *
     * @throws Exception
     * @throws QUI\ERP\Accounting\Invoice\Exception
     */
    public function getInvoice(): QUI\ERP\Accounting\Invoice\Invoice|QUI\ERP\Accounting\Invoice\InvoiceTemporary
    {
        if ($this->orderId) {
            $Order = Handler::getInstance()->get($this->getOrderId());
            return $Order->getInvoice();
        }

        throw new QUI\ERP\Accounting\Invoice\Exception(
            ['quiqqer/invoice', 'exception.invoice.not.found'],
            404
        );
    }

    /**
     * Set the successful status to the order
     * is overwritten here, because the order in process checks if there is an order.
     * if so, do not fire the event quiqqerOrderSuccessful twice, the order already fires this
     *
     * @throws Exception
     * @throws QUI\ExceptionStack
     */
    public function setSuccessfulStatus(): void
    {
        if ($this->orderId) {
            $Order = Handler::getInstance()->get($this->getOrderId());
            $Order->setSuccessfulStatus();

            return;
        }

        if ($this->successful === 1) {
            return;
        }

        parent::setSuccessfulStatus();
    }

    /**
     * Set Order payment status (paid_status)
     *
     * @param int $status
     * @param bool $force - default = false, if true, set payment status will be set in any case
     *
     * @return void
     * @throws Exception
     */
    public function setPaymentStatus(int $status, bool $force = false): void
    {
        if ($this->orderId) {
            $Order = Handler::getInstance()->get($this->getOrderId());
            $Order->setPaymentStatus($status);

            return;
        }

        $oldPaidStatus = $this->getAttribute('paid_status');

        if ($oldPaidStatus == $status && $force === false) {
            return;
        }

        QUI::getDataBase()->update(
            Handler::getInstance()->tableOrderProcess(),
            ['paid_status' => $status],
            ['hash' => $this->getUUID()]
        );

        QUI\ERP\Debug::getInstance()->log(
            'OrderInProcess:: Paid Status changed to ' . $status
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

            if ($this->isApproved()) {
                $this->triggerApprovalEvent();
            }
        }
    }

    /**
     * @return void
     */
    protected function saveFrontendMessages(): void
    {
        try {
            QUI::getDataBase()->update(
                Handler::getInstance()->tableOrderProcess(),
                ['frontendMessages' => $this->FrontendMessage->toJSON()],
                ['hash' => $this->getUUID()]
            );
        } catch (Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage(), [
                'order' => $this->getUUID(),
                'orderHash' => $this->getUUID(),
                'orderType' => $this->getType(),
                'action' => 'Order->clearFrontendMessages'
            ]);
        }
    }
}
