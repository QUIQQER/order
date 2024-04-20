<?php

/**
 * This file contains \QUI\ERP\Order\Console\SendOrderConfirmationMail
 *
 * https://dev.quiqqer.com/quiqqer/order/wikis/Home/Send-order-confirmation-mail-console-tool
 */

namespace QUI\ERP\Order\Console;

use Exception;
use QUI;

class SendOrderConfirmationMail extends QUI\System\Console\Tool
{
    public function __construct()
    {
        $this->setName('order:sendOrderConfirmationMail')
            ->setDescription(
                QUI::getLocale()->get('quiqqer/order', 'console.SendOrderConfirmationMail.desc')
            )
            ->addArgument(
                'orderId',
                QUI::getLocale()->get('quiqqer/order', 'console.SendOrderConfirmationMail.help.orderId')
            );
    }

    public function execute(): void
    {
        $Handler = QUI\ERP\Order\Handler::getInstance();
        $orderId = $this->getArgument('orderId');

        // is order id with trailing character (order prefix)?
        if (strrpos($orderId, '-')) {
            $orderId = substr($orderId, strrpos($orderId, '-') + 1);
        }

        try {
            $Order = $Handler->get($orderId);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            $this->writeLn(
                QUI::getLocale()->get(
                    'quiqqer/order',
                    'console.SendOrderConfirmationMail.message.noOrderFound',
                    ['orderId' => $this->getArgument('orderId')]
                )
            );
            $this->writeLn();

            exit(1);
        }

        // todo set email to send
        /*$Customer = $Order->getCustomer();
        $User = QUI::getUsers()->getUserByName($username);
        $email    = $Customer->getAttribute('email');*/

        try {
            QUI\ERP\Order\Mail::sendOrderConfirmationMail($Order);
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            $this->writeLn(
                QUI::getLocale()->get(
                    'quiqqer/order',
                    'console.SendOrderConfirmationMail.message.cantSendEmail'
                )
            );

            exit(1);
        }

        $this->writeLn(
            QUI::getLocale()->get(
                'quiqqer/order',
                'console.SendOrderConfirmationMail.message.success'
            )
        );
        $this->writeLn();

        exit(0);
    }
}
