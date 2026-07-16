<?php

/**
 * This file contains QUI\ERP\Order\NumberRanges\Order
 */

namespace QUI\ERP\Order\NumberRanges;

use QUI;
use QUI\ERP\Api\NumberRangeInterface;
use QUI\ERP\Order\Settings;

/**
 * Class Order
 * - Order range
 *
 * @package QUI\ERP\Order\NumberRanges
 */
class Order implements NumberRangeInterface
{
    /**
     * @param null|QUI\Locale $Locale
     *
     * @return string
     */
    public function getTitle(null | QUI\Locale $Locale = null): string
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/order', 'order.numberrange.title');
    }

    /**
     * Return the current start range value
     *
     * @return int
     */
    public function getRange(): int
    {
        $Config = Settings::getConfig();
        $orderId = $Config->getValue('order', 'orderCurrentIdIndex');

        if (empty($orderId)) {
            return 1;
        }

        return (int)$orderId + 1;
    }

    /**
     * @param int $range
     */
    public function setRange(int $range): void
    {
        $Config = Settings::getConfig();
        $Config->set('order', 'orderCurrentIdIndex', $range);
        $Config->save();
    }
}
