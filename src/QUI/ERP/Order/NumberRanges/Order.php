<?php

/**
 * This file contains QUI\ERP\Order\NumberRanges\Order
 */

namespace QUI\ERP\Order\NumberRanges;

use QUI;
use QUI\ERP\Api\NumberRangeInterface;

use function is_numeric;

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
    public function getTitle($Locale = null)
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
    public function getRange()
    {
        $Table = QUI::getDataBase()->table();
        $Handler = QUI\ERP\Order\Handler::getInstance();

        return $Table->getAutoIncrementIndex($Handler->table());
    }

    /**
     * @param int $range
     */
    public function setRange($range)
    {
        if (!is_numeric($range)) {
            return;
        }

        $PDO = QUI::getDataBase()->getPDO();
        $Handler = QUI\ERP\Order\Handler::getInstance();
        $tableName = $Handler->table();

        $Statement = $PDO->prepare(
            "ALTER TABLE {$tableName} AUTO_INCREMENT = " . (int)$range
        );

        $Statement->execute();
    }
}
