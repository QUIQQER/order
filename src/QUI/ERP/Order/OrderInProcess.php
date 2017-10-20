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
     * Order constructor.
     *
     * @param string|integer $orderId - Order-ID
     */
    public function __construct($orderId)
    {
        parent::__construct(
            Handler::getInstance()->getOrderProcessData($orderId)
        );
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

        $SystemUser = QUI::getUsers()->getSystemUser();

        $this->save($SystemUser);

        $Order = Factory::getInstance()->create($SystemUser);

        // bind the new order to the process order
        QUI::getDataBase()->update(
            Handler::getInstance()->tableOrderProcess(),
            array('order_id' => $Order->getId()),
            array('id' => $this->getId())
        );

        // copy the data to the order
        $data                     = $this->getDataForSaving();
        $data['order_process_id'] = $this->getId();

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            $data,
            array('id' => $Order->getId())
        );
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

        //payment
        $paymentId     = null;
        $paymentMethod = null;

        $Payment = $this->getPayment();

        if ($Payment) {
            $paymentId     = $Payment->getId();
            $paymentMethod = $Payment->getPaymentType()->getTitle();
        }

        return array(
            'customerId'      => $this->customerId,
            'customer'        => json_encode($customer),
            'addressInvoice'  => $InvoiceAddress->toJSON(),
            'addressDelivery' => $deliveryAddress,

            'articles' => $this->Articles->toJSON(),
            'comments' => $this->Comments->toJSON(),
            'data'     => json_encode($this->data),

            'payment_id'      => $paymentId,
            'payment_method'  => $paymentMethod,
            'payment_time'    => null,
            'payment_data'    => '', // verschlüsselt
            'payment_address' => ''  // verschlüsselt
        );
    }
}
