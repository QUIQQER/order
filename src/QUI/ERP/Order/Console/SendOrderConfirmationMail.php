<?php
/**
 * This file contains \QUI\ERP\Order\Console\SendOrderConfirmationMail
 */

namespace QUI\ERP\Order\Console;

use QUI;

class SendOrderConfirmationMail extends QUI\System\Console\Tool
{
    public function __construct()
    {
        $this->setName('order:sendOrderConfirmationMail')
            ->setDescription(
                QUI::getLocale()->get('quiqqer/order', 'console.SendOrderConfirmationMail.desc')
            )
            ->addArgument('orderNumber',
                QUI::getLocale()->get('quiqqer/order', 'console.SendOrderConfirmationMail.help.orderNumber')
            );
    }

    public function execute()
    {

        $this->writeLn("this is a Test");                    // Hier Wird eine Test Message in die Konsole ausgegeben
        $this->writeLn("Hello World");                       // Hier Wird eine Test Message in die Konsole ausgegeben
        $this->writeLn();                                    // Hier wird eine Leerzeile in der Konsole ausgegeben

        // execute bereich

        $this->writeLn($this->getArgument('table'));         // Hier wird das eingefügte '--table=""' Argument in der Konsole ausgegeben
        $this->writeLn();
        $this->writeLn();
    }
}
