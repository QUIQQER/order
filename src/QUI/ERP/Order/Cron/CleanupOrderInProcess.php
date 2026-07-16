<?php

/**
 * This file contains QUI\ERP\Order\Cron\CleanupOrderInProcess
 */

namespace QUI\ERP\Order\Cron;

use QUI;
use QUI\Utils\Doctrine;

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
     * @param array<string, mixed> $params - cron parameter
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

        if ($c_date === false) {
            return;
        }

        $c_date = date('Y-m-d H:i:s', $c_date);

        $Handler = QUI\ERP\Order\Handler::getInstance();
        $table   = $Handler->tableOrderProcess();

        $Connection = QUI::getDataBaseConnection();
        $orderIds = $Connection->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier($table))
            ->where(Doctrine::quoteIdentifier('c_date') . ' <= :date')
            ->setParameter('date', $c_date)
            ->executeQuery()
            ->fetchFirstColumn();

        foreach ($orderIds as $orderId) {
            $Connection->delete($table, ['id' => $orderId]);
        }

        // unique orders deletion
        // BasketConditions::TYPE_2 orders
        $c_date = strtotime('-1 day');
        $c_date = date('Y-m-d H:i:s', $c_date);

        try {
            $orderIds = $Connection->createQueryBuilder()
                ->select(Doctrine::quoteIdentifier('id'))
                ->from(Doctrine::quoteIdentifier($table))
                ->where(Doctrine::quoteIdentifier('data') . ' LIKE :search')
                ->andWhere(Doctrine::quoteIdentifier('c_date') . ' <= :date')
                ->setParameter('search', '%"basketConditionOrder":2%')
                ->setParameter('date', $c_date)
                ->executeQuery()
                ->fetchFirstColumn();

            foreach ($orderIds as $orderId) {
                $Connection->delete($table, ['id' => $orderId]);
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }
}
