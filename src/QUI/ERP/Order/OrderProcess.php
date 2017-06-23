<?php

/**
 * This file contains QUI\ERP\Order\OrderProcess
 */

namespace QUI\ERP\Order;

use QUI;

/**
 * Class OrderProcess
 *
 * @package QUI\ERP\Order
 */
class OrderProcess extends AbstractOrder
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
     * @param null $PermissionUser
     */
    public function update($PermissionUser = null)
    {
        // TODO: Implement update() method.
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
        $isAllowedToDelete = function () use ($PermissionUser) {
            if ($this->cUser === QUI::getUserBySession()->getId()) {
                return true;
            }

            if ($PermissionUser && $this->cUser === $PermissionUser->getId()) {
                return true;
            }

            return false;
        };

        if ($isAllowedToDelete() === false) {
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
     * Create the order
     */
    public function createOrder()
    {
    }
}
