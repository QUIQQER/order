<?php
/**
 * SendOrderConfirmationMail.php
 */

namespace QUI\ERP\Order\Console;

use QUI;

class SendOrderConfirmationMail extends QUI\System\Console\Tool
{
    public function __construct()
    {
        $this->setName('order:send-order-confirmation-mail')               // Hier wird der Packet Name des Konsolen Tools übergeben
        ->setDescription(QUI::getLocale()->get('quiqqer/order', 'console.SendOrderConfirmationMail.desc'))         // Dies ist eine Beschreibung für das Konsolen Tool
        ->addArgument('delete-tables', 'Beschreibung');    // Dies ist eine Beschreibung für die in der Konsole ausführbaren Befehle oder Argumente
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
