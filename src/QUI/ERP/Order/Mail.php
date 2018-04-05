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
    public static function sendOrderCreateMail(Order $Order)
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
            'orderHash' => $Order->getHash()
        ]);

        $Customer = $Order->getCustomer();


        // mail
        $Mailer = QUI::getMailManager()->getMailer();
        $Mailer->addRecipient($email);

        $Mailer->setSubject(
            QUI::getLocale()->get('quiqqer/order', 'order.confirmation.subject', [
                'orderId' => $Order->getId()
            ])
        );

        $Mailer->setBody(
            QUI::getLocale()->get('quiqqer/order', 'order.confirmation.body', [
                'orderId'  => $Order->getId(),
                'order'    => QUI\ControlUtils::parse($OrderControl),
                'user'     => $Customer->getName(),
                'username' => $Customer->getUsername(),
            ])
        );

        $Mailer->send();
    }
}
