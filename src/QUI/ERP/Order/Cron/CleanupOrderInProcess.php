<?php

/**
 * This file contains QUI\ERP\Order\Cron\CleanupOrderInProcess
 */

namespace QUI\ERP\Order\Cron;

use QUI;

/**
 * Class CleanupOrderInProcess
 *
 * This cron cleans old orders that have not been executed and are no longer needed.
 * This cron does not influence any orders but only orders in process.
 * Orders in process are canceled orders.
 *
 * @package QUI\ERP\Order\Crons
 */
class CleanupOrderInProcess
{
    /**
     * Execute the cron
     *
     * @param array $params - cron parameter
     * @throws QUI\Exception
     */
    public static function run($params = [])
    {
        $days = 14; // 14 days

        if (!is_array($params)) {
            $params = [];
        }

        if (!empty($params['days'])) {
            $days = (int)$params['days'];
        }

        $days   = $days * -1;
        $c_date = \strtotime($days.' day');
        $c_date = \date('Y-m-d H:i:s', $c_date);

        $Handler = QUI\ERP\Order\Handler::getInstance();
        $table   = $Handler->tableOrderProcess();

        $orders = QUI::getDataBase()->fetch([
            'from'  => $table,
            'where' => [
                'c_date' => [
                    'value' => $c_date,
                    'type'  => '<='
                ]
            ]
        ]);

        foreach ($orders as $order) {
            QUI::getDataBase()->delete($table, [
                'id' => $order['id']
            ]);
        }
    }
}
