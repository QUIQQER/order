<?php

/**
 * This file contains QUI\ERP\Order\Mail
 */

namespace QUI\ERP\Order;

use QUI;

/**
 * Class Mail
 *
 * @package QUI\ERP\Order
 */
class Mail
{
    /**
     * confirmation mail for the customer
     *
     * @param Order $Order
     * @throws QUI\Exception
     */
    public static function sendOrderConfirmationMail(Order $Order)
    {
        $Customer = $Order->getCustomer();
        $email    = $Customer->getAttribute('email');

        if (empty($email)) {
            QUI\System\Log::writeRecursive(
                QUI::getLocale()->get('quiqqer/order', 'message.order.missing.customer.mail', [
                    'orderId'    => $Order->getId(),
                    'customerId' => $Customer->getId()
                ])
            );

            return;
        }

        // create order data
        $OrderControl = new QUI\ERP\Order\Controls\Order\Order([
            'orderHash' => $Order->getHash(),
            'template'  => 'OrderLikeBasket'
        ]);

        $Address  = null;
        $Customer = $Order->getCustomer();

        $user = $Customer->getName();
        $user = \trim($user);

        if (empty($user)) {
            $Address = $Customer->getAddress();
            $user    = $Address->getName();
        }

        // mail
        $Mailer = QUI::getMailManager()->getMailer();
        $Mailer->addRecipient($email);

        if (Settings::getInstance()->get('order', 'sendOrderConfirmationToAdmin') && QUI::conf('mail', 'admin_mail')) {
            $Mailer->addBCC(
                QUI::conf('mail', 'admin_mail')
            );
        }

        $Mailer->setSubject(
            QUI::getLocale()->get('quiqqer/order', 'order.confirmation.subject', [
                'orderId' => $Order->getPrefixedId()
            ])
        );

        $Engine = QUI::getTemplateManager()->getEngine();
        $Order  = $OrderControl->getOrder();

        $Articles = $Order->getArticles()->toUniqueList();
        $Articles->hideHeader();

        $Shipping        = null;
        $DeliveryAddress = null;

        if ($Order->getShipping()) {
            $Shipping        = QUI\ERP\Shipping\Shipping::getInstance()->getShippingByObject($Order);
            $DeliveryAddress = $Shipping->getAddress();
        }

        $Engine->assign([
            'Shipping'        => $Shipping,
            'DeliveryAddress' => $DeliveryAddress,
            'InvoiceAddress'  => $Order->getInvoiceAddress(),
            'Payment'         => $Order->getPayment(),

            'Order'    => $Order,
            'Articles' => $Articles,
            'Address'  => $Address,

            'message' => QUI::getLocale()->get('quiqqer/order', 'order.confirmation.body', [
                'orderId'  => $Order->getPrefixedId(),
                'user'     => $user,
                'username' => $Customer->getUsername(),
            ])
        ]);

        $Mailer->setBody(
            $Engine->fetch(\dirname(__FILE__).'/MailTemplates/orderConfirmation.html')
        );

        $Mailer->send();
    }

    /**
     * confirmation mail for the admin
     *
     * @param Order $Order
     */
    public static function sendAdminOrderConfirmationMail(Order $Order)
    {
        $email = QUI::conf('mail', 'admin_mail');

        if (empty($email)) {
            QUI\System\Log::addAlert(
                QUI::getLocale()->get('quiqqer/order', 'message.order.missing.admin.mail')
            );

            return;
        }

        // create order data
        $OrderControl = new QUI\ERP\Order\Controls\Order\Order([
            'orderHash' => $Order->getHash(),
            'template'  => 'OrderLikeBasket'
        ]);

        $Address  = null;
        $Customer = $Order->getCustomer();

        $user = $Customer->getName();
        $user = \trim($user);

        if (empty($user)) {
            $Address = $Customer->getAddress();
            $user    = $Address->getName();
        }

        // mail
        $Mailer = QUI::getMailManager()->getMailer();
        $Mailer->addRecipient($email);

        $Mailer->setSubject(
            QUI::getLocale()->get('quiqqer/order', 'order.confirmation.admin.subject', [
                'orderId' => $Order->getPrefixedId()
            ])
        );

        try {
            $Engine = QUI::getTemplateManager()->getEngine();
            $Order  = $OrderControl->getOrder();

            $Articles = $Order->getArticles()->toUniqueList();
            $Articles->hideHeader();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addAlert(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.confirmation.admin', [
                    'orderId' => $Order->getPrefixedId(),
                    'message' => $Exception->getMessage()
                ])
            );

            return;
        }


        $Shipping        = null;
        $DeliveryAddress = null;

        if ($Order->getShipping()) {
            $Shipping        = QUI\ERP\Shipping\Shipping::getInstance()->getShippingByObject($Order);
            $DeliveryAddress = $Shipping->getAddress();
        }

        $Engine->assign([
            'Shipping'        => $Shipping,
            'DeliveryAddress' => $DeliveryAddress,
            'InvoiceAddress'  => $Order->getInvoiceAddress(),
            'Payment'         => $Order->getPayment(),

            'Order'    => $Order,
            'Articles' => $Articles,
            'Address'  => $Address,

            'message' => QUI::getLocale()->get('quiqqer/order', 'order.confirmation.admin.body', [
                'orderId'  => $Order->getPrefixedId(),
                'userId'   => $Customer->getId(),
                'username' => $user
            ])
        ]);

        $Mailer->setBody(
            $Engine->fetch(\dirname(__FILE__).'/MailTemplates/orderConfirmationAdmin.html')
        );

        try {
            $Mailer->send();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addAlert(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.confirmation.admin', [
                    'orderId' => $Order->getPrefixedId(),
                    'message' => $Exception->getMessage()
                ])
            );

            return;
        }
    }
}
