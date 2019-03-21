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
     * confirmation of order mail
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

        $Customer = $Order->getCustomer();
        $user     = $Customer->getName();
        $user     = \trim($user);

        if (empty($user)) {
            $Address = $Customer->getAddress();
            $user    = $Address->getName();
        }

        // mail
        $Mailer = QUI::getMailManager()->getMailer();
        $Mailer->addRecipient($email);

        if (Settings::getInstance()->get('order', 'sendOrderConfirmationToAdmin')
            && QUI::conf('mail', 'admin_mail')) {
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

        $Engine->assign([
            'Order'    => $Order,
            'Articles' => $Articles,
            'message'  => QUI::getLocale()->get('quiqqer/order', 'order.confirmation.body', [
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
}
