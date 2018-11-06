<?php

/**
 * This file contains QUI\ERP\Order\OrderInProcess
 */

namespace QUI\ERP\Order;

use QUI;

/**
 * Class OrderInProcess
 *
 * this is a order in process and not the ordering process
 * This order is currently being processed by the user
 *
 * @package QUI\ERP\Order
 */
class OrderInProcess extends AbstractOrder implements OrderInterface
{
    /**
     * @var null|integer
     */
    protected $orderId = null;

    /**
     * Order constructor.
     *
     * @param string|integer $orderId - Order-ID
     *
     * @throws QUI\Erp\Order\Exception
     * @throws QUI\ERP\Exception
     */
    public function __construct($orderId)
    {
        $data = Handler::getInstance()->getOrderProcessData($orderId);

        parent::__construct($data);

        $this->orderId = (int)$data['order_id'];

        // check if a order for the processing order exists
        try {
            Handler::getInstance()->get($this->orderId);
        } catch (QUI\Exception $Exception) {
            $this->orderId = null;
        }
    }

    /**
     * Refresh the data, makes a database call
     *
     * @throws Exception
     * @throws QUI\ERP\Exception
     */
    public function refresh()
    {
        $this->setDataBaseData(
            Handler::getInstance()->getOrderProcessData($this->getId())
        );
    }

    /**
     * @return int|null
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * Return the real order id for the customer
     * For the customer this method returns the hash, so he has an association to the real order
     *
     * @return string
     */
    public function getPrefixedId()
    {
        return $this->getHash();
    }

    /**
     * Alias for update
     *
     * @param null $PermissionUser
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Exception
     */
    public function save($PermissionUser = null)
    {
        $this->update($PermissionUser);
    }

    /**
     * @param null $PermissionUser
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Exception
     */
    public function update($PermissionUser = null)
    {
        if ($this->hasPermissions($PermissionUser) === false) {
            throw new QUI\Permissions\Exception(
                QUI::getLocale()->get('quiqqer/system', 'exception.no.permission'),
                403
            );
        }

        $data = $this->getDataForSaving();

        QUI::getEvents()->fireEvent(
            'quiqqerOrderProcessUpdateBegin',
            [$this, $data]
        );

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

        QUI::getDataBase()->update(
            Handler::getInstance()->tableOrderProcess(),
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

        QUI::getEvents()->fireEvent(
            'quiqqerOrderProcessUpdate',
            [$this, $data]
        );
    }

    /**
     * Add price factors to the order
     *
     * @param array $priceFactors
     * @throws QUI\Exception
     */
    public function addPriceFactors($priceFactors = [])
    {
        $Basket = new QUI\ERP\Order\Basket\BasketOrder(
            $this->getHash(),
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
            } catch (QUI\Exception $Exception) {
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
     * @throws QUI\Exception
     * @throws QUI\ERP\Exception
     * @throws QUI\Permissions\Exception
     */
    protected function calculatePayments()
    {
        QUI\ERP\Debug::getInstance()->log('OrderInProcess:: Calculate Payments');

        $User = QUI::getUserBySession();

        // old status
        $oldPaidStatus = $this->getAttribute('paid_status');
        $calculations  = QUI\ERP\Accounting\Calc::calculatePayments($this);

        switch ($this->getAttribute('paid_status')) {
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
                [
                    'username' => $User->getName(),
                    'uid'      => $User->getId()
                ]
            )
        );

        if (is_array($calculations['paidData'])) {
            $calculations['paidData'] = json_encode($calculations['paidData']);
        }

        QUI::getDataBase()->update(
            Handler::getInstance()->tableOrderProcess(),
            [
                'paid_data'   => $calculations['paidData'],
                'paid_date'   => $calculations['paidDate'],
                'paid_status' => $calculations['paidStatus']
            ],
            ['id' => $this->getId()]
        );

        $Order = $this;

        // create order, if the payment status is paid and no order exists
        if ($this->getAttribute('paid_status') === self::PAYMENT_STATUS_PAID && !$this->orderId) {
            $Order = $this->createOrder();
        }

        // Payment Status has changed
        if ($oldPaidStatus == $calculations['paidStatus']) {
            return;
        }

        QUI::getEvents()->fireEvent(
            'onQuiqqerOrderPaymentStatusChanged',
            [$Order, $calculations['paidStatus'], $oldPaidStatus]
        );

        QUI\ERP\Debug::getInstance()->log(
            'OrderInProcess:: Paid Status changed to '.$calculations['paidStatus']
        );
    }

    /**
     * Delete the processing order
     * The user itself or a super can delete it
     *
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @throws QUI\Permissions\Exception
     */
    public function delete($PermissionUser = null)
    {
        if ($this->hasPermissions($PermissionUser) === false) {
            throw new QUI\Permissions\Exception(
                QUI::getLocale()->get('quiqqer/system', 'exception.no.permission'),
                403
            );
        }

        QUI::getDataBase()->delete(
            Handler::getInstance()->tableOrderProcess(),
            ['id' => $this->getId()]
        );
    }

    /**
     * An order in process is never finished
     *
     * @return bool
     */
    public function isPosted()
    {
        return false;
    }

    /**
     * Create the order
     *
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return Order
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Exception
     * @throws Exception
     */
    public function createOrder($PermissionUser = null)
    {
        QUI\ERP\Debug::getInstance()->log('OrderInProcess:: Create Order');

        if ($this->hasPermissions($PermissionUser) === false) {
            throw new QUI\Permissions\Exception(
                QUI::getLocale()->get('quiqqer/system', 'exception.no.permission'),
                403
            );
        }

        if ($this->orderId) {
            return Handler::getInstance()->get($this->orderId);
        }

        $SystemUser = QUI::getUsers()->getSystemUser();

        $this->save($SystemUser);

        $Order = Factory::getInstance()->create($SystemUser, $this->getHash());

        // bind the new order to the process order
        QUI::getDataBase()->update(
            Handler::getInstance()->tableOrderProcess(),
            ['order_id' => $Order->getId()],
            ['id' => $this->getId()]
        );

        $this->orderId = $Order->getId();

        // copy the data to the order
        $data                     = $this->getDataForSaving();
        $data['id_prefix']        = $Order->getIdPrefix();
        $data['id_str']           = $Order->getPrefixedId();
        $data['order_process_id'] = $this->getId();
        $data['c_user']           = $this->cUser;
        $data['paid_status']      = $this->getAttribute('paid_status');
        $data['paid_date']        = $this->getAttribute('paid_date');
        $data['paid_data']        = $this->getAttribute('paid_data');
        $data['successful']       = $this->successful;

        if (empty($data['paid_date'])) {
            $data['paid_date'] = null;
        }

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            $data,
            ['id' => $Order->getId()]
        );

        // get the order with new data
        $Order->refresh();

        QUI\ERP\Debug::getInstance()->log('OrderInProcess:: Order created');
        QUI\ERP\Debug::getInstance()->log('OrderInProcess:: Order calculatePayments');

        try {
            // reset & recalculation
            $Order->setAttribute('paid_status', QUI\ERP\Accounting\Invoice\Invoice::PAYMENT_STATUS_OPEN);
            $Order->calculatePayments();

            if ($data['paid_status'] !== $Order->getAttribute('paid_status')) {
                $Order->save();
            }
        } catch (QUI\Exception $Exception) {
            if (defined('QUIQQER_DEBUG')) {
                QUI\System\Log::writeException($Exception);
            }
        }

        $Payment = $Order->getPayment();

        if ($Payment->isSuccessful($Order->getHash())) {
            $Order->setSuccessfulStatus();
            $this->setSuccessfulStatus();
        }

        try {
            QUI::getEvents()->fireEvent('quiqqerOrderCreated', [$Order]);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }


        // create invoice?
        if (Settings::getInstance()->createInvoiceOnOrder()) {
            $Order->createInvoice();

            return $Order;
        }

        if (Settings::getInstance()->createInvoiceByPayment()
            && $Order->getPayment()->isSuccessful($Order->getHash())) {
            $Order->createInvoice();

            return $Order;
        }

        if (Settings::getInstance()->createInvoiceByPayment()
            && $Order->getPayment()->isSuccessful($Order->getHash())) {
            $Order->createInvoice();
        }

        return $Order;
    }

    /**
     * Has the user permissions to do things
     *
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return bool
     */
    protected function hasPermissions($PermissionUser = null)
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        if ($this->cUser === $PermissionUser->getId()) {
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
     * @throws QUI\Exception
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
            $customer = QUI\ERP\Utils\User::filterCustomerAttributes($Customer->getAttributes());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        // status
        $status = 0;

        if ($this->status) {
            $status = $this->status;
        }

        //payment
        $paymentId     = null;
        $paymentMethod = null;

        $Payment = $this->getPayment();

        try {
            if ($Payment) {
                $paymentId     = $Payment->getId();
                $paymentMethod = $Payment->getPaymentType()->getTitle();
            }
        } catch (QUI\Exception $Exception) {
        }

        return [
            'customerId'      => $this->customerId,
            'customer'        => json_encode($customer),
            'addressInvoice'  => $InvoiceAddress->toJSON(),
            'addressDelivery' => $deliveryAddress,

            'articles'   => $this->Articles->toJSON(),
            'comments'   => $this->Comments->toJSON(),
            'history'    => $this->History->toJSON(),
            'data'       => json_encode($this->data),
            'status'     => $status,
            'successful' => $this->successful,

            'payment_id'      => $paymentId,
            'payment_method'  => $paymentMethod,
            'payment_time'    => null,
            'payment_data'    => QUI\Security\Encryption::encrypt(
                json_encode($this->paymentData)
            ), // verschlüsselt
            'payment_address' => ''  // verschlüsselt
        ];
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
        if ($this->hasPermissions($PermissionUser) === false) {
            throw new QUI\Permissions\Exception(
                QUI::getLocale()->get('quiqqer/system', 'exception.no.permission'),
                403
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
                'paid_data'   => '',
                'paid_date'   => '',

                'payment_id'      => '',
                'payment_method'  => '',
                'payment_data'    => '',
                'payment_time'    => null,
                'payment_address' => '',
            ],
            ['id' => $this->getId()]
        );

        $this->refresh();

        QUI::getEvents()->fireEvent('quiqqerOrderClear', [$this]);
    }

    /**
     * @return bool
     */
    public function hasInvoice()
    {
        return false;
    }

    /**
     * @return QUI\ERP\Accounting\Invoice\Invoice|void
     * @throws QUI\ERP\Accounting\Invoice\Exception
     */
    public function getInvoice()
    {
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
     * @throws QUI\Exception
     * @throws QUI\ExceptionStack
     */
    public function setSuccessfulStatus()
    {
        if ($this->successful === 1) {
            return;
        }

        if ($this->orderId) {
            return;
        }

        parent::setSuccessfulStatus();
    }
}
