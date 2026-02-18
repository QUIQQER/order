<?php

/**
 * This file contains QUI\ERP\Order\Cron\CleanupOrderInProcess
 */

namespace QUI\ERP\Order\Cron;

use Exception;
use PDO;
use QUI;

use function date;
use function strtotime;

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
    public static function run(array $params = []): void
    {
        $days = 14; // 14 days

        if (!empty($params['days'])) {
            $days = (int)$params['days'];
        }

        $days   = $days * -1;
        $c_date = strtotime($days . ' day');
        $c_date = date('Y-m-d H:i:s', $c_date);

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

        // unique orders deletion
        // BasketConditions::TYPE_2 orders
        $PDO    = QUI::getDataBase()->getPDO();
        $c_date = strtotime('-1 day');
        $c_date = date('Y-m-d H:i:s', $c_date);

        $query     = "SELECT * FROM $table where data LIKE :search AND c_date <= :date";
        $Statement = $PDO->prepare($query);

        $Statement->bindValue('search', '%"basketConditionOrder":2%');
        $Statement->bindValue('date', $c_date);
        $Statement->execute();

        try {
            $result = $Statement->fetchAll(PDO::FETCH_ASSOC);

            foreach ($result as $orderData) {
                QUI::getDataBase()->delete($table, [
                    'id' => $orderData['id']
                ]);
            }
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            QUI\System\Log::writeRecursive($query);
        }
    }
}
