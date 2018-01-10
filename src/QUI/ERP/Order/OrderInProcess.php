<?php

/**
 * This file contains QUI\ERP\Order\OrderInProcess
 */

namespace QUI\ERP\Order;

use QUI;

/**
 * Class OrderInProcess
 *
 * @package QUI\ERP\Order
 */
class OrderInProcess extends AbstractOrder
{
    /**
     * @var null|integer
     */
    protected $orderId = null;

    /**
     * Order constructor.
     *
     * @param string|integer $orderId - Order-ID
     */
    public function __construct($orderId)
    {
        $data = Handler::getInstance()->getOrderProcessData($orderId);

        parent::__construct($data);

        $this->orderId = (int)$data['order_id'];

        try {
            Handler::getInstance()->get($this->orderId);
        } catch (QUI\ERP\Order\Exception $Exception) {
            $this->orderId = null;
        }
    }

    /**
     * Alias for update
     *
     * @param null $PermissionUser
     * @throws QUI\Permissions\Exception
     */
    public function save($PermissionUser = null)
    {
        if ($this->hasPermissions($PermissionUser) === false) {
            throw new QUI\Permissions\Exception(
                QUI::getLocale()->get('quiqqer/system', 'exception.no.permission'),
                403
            );
        }

        $this->update($PermissionUser);
    }

    /**
     * @param null $PermissionUser
     * @throws QUI\Permissions\Exception
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
            array($this, $data)
        );

        QUI::getDataBase()->update(
            Handler::getInstance()->tableOrderProcess(),
            $data,
            array('id' => $this->getId())
        );

        QUI::getEvents()->fireEvent(
            'quiqqerOrderProcessUpdate',
            array($this, $data)
        );
    }

    /**
     * Calculates the payment for the order
     */
    protected function calculatePayments()
    {
        $User = QUI::getUserBySession();

        // old status
        $oldPaidStatus = $this->getAttribute('paid_status');

        QUI\ERP\Accounting\Calc::calculatePayments($this);

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
                array(
                    'username' => $User->getName(),
                    'uid'      => $User->getId()
                )
            )
        );

        QUI::getDataBase()->update(
            Handler::getInstance()->tableOrderProcess(),
            array(
                'paid_data'   => $this->getAttribute('paid_data'),
                'paid_date'   => $this->getAttribute('paid_date'),
                'paid_status' => $this->getAttribute('paid_status')
            ),
            array('id' => $this->getId())
        );

        // Payment Status has changed
        if ($oldPaidStatus != $this->getAttribute('paid_status')) {
            QUI::getEvents()->fireEvent(
                'onQuiqqerOrderAddComment',
                array($this, $this->getAttribute('paid_status'), $oldPaidStatus)
            );
        }
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
            array('id' => $this->getId())
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
     * @throws QUI\Permissions\Exception
     */
    public function createOrder($PermissionUser = null)
    {
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

        $Order = Factory::getInstance()->create($SystemUser);

        // bind the new order to the process order
        QUI::getDataBase()->update(
            Handler::getInstance()->tableOrderProcess(),
            array(
                'order_id' => $Order->getId(),
                'hash'     => $Order->getHash()
            ),
            array('id' => $this->getId())
        );

        $this->orderId = $Order->getId();

        // copy the data to the order
        $data                     = $this->getDataForSaving();
        $data['order_process_id'] = $this->getId();

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            $data,
            array('id' => $Order->getId())
        );

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
        if ($this->cUser === QUI::getUserBySession()->getId()) {
            return true;
        }

        if ($PermissionUser && $this->cUser === $PermissionUser->getId()) {
            return true;
        }

        //@todo permissoins prüfen

        return false;
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

        // status
        $status = self::STATUS_CREATED;

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

        return array(
            'customerId'      => $this->customerId,
            'customer'        => json_encode($customer),
            'addressInvoice'  => $InvoiceAddress->toJSON(),
            'addressDelivery' => $deliveryAddress,

            'articles' => $this->Articles->toJSON(),
            'comments' => $this->Comments->toJSON(),
            'data'     => json_encode($this->data),
            'status'   => $status,

            'payment_id'      => $paymentId,
            'payment_method'  => $paymentMethod,
            'payment_time'    => null,
            'payment_data'    => '', // verschlüsselt
            'payment_address' => ''  // verschlüsselt
        );
    }
}
