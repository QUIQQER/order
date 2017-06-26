<?php

/**
 * This file contains QUI\ERP\Order\Mailer
 */

namespace QUI\ERP\Order;

use QUI;

/**
 * Class Mailer
 * - Helps an order to send status mails
 *
 * @package QUI\ERP\Order
 */
class Mailer extends QUI\Utils\Singleton
{
    /**
     * Sends a confirmation mail to recipient(s)
     *
     * @param Order $Order
     * @param string|array $email
     */
    public function sendConfirmation($Order, $email)
    {

    }
}
