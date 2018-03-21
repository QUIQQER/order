<?php

/**
 * This file contains QUI\ERP\Order\Search
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\Utils\Singleton;

use QUI\ERP\Accounting\Payments\Payments as Payments;

/**
 * Class Search
 * @package QUI\ERP\Order
 */
class Search extends Singleton
{
    /**
     * @var array
     */
    protected $filter = [];

    /**
     * @var array
     */
    protected $limit = [0, 20];

    /**
     * @var string
     */
    protected $order = 'id DESC';

    /**
     * @var array
     */
    protected $allowedFilters = [
        'from',
        'to'
    ];

    /**
     * @var array
     */
    protected $cache = [];

    /**
     * Set a filter
     *
     * @param string $filter
     * @param string|array $value
     */
    public function setFilter($filter, $value)
    {
        $keys = array_flip($this->allowedFilters);

        if (!isset($keys[$filter])) {
            return;
        }

        if (!is_array($value)) {
            $value = [$value];
        }

//        foreach ($value as $val) {
//
//            if ($filter === 'from' && is_numeric($val)) {
//                $val = date('Y-m-d 00:00:00', $val);
//            }
//
//            if ($filter === 'to' && is_numeric($val)) {
//                $val = date('Y-m-d 23:59:59', $val);
//            }
//
//            $this->filter[] = array(
//                'filter' => $filter,
//                'value'  => $val
//            );
//        }
    }

    /**
     * Clear all filters
     */
    public function clearFilter()
    {
        $this->filter = [];
    }

    /**
     * Set the limit
     *
     * @param string $from
     * @param string $to
     */
    public function limit($from, $to)
    {
        $this->limit = [(int)$from, (int)$to];
    }

    /**
     * Set the order
     *
     * @param $order
     */
    public function order($order)
    {
        switch ($order) {
            case 'id':
            case 'id ASC':
            case 'id DESC':
                $this->order = $order;
                break;
        }
    }

    /**
     * Execute the search and return the order list
     *
     * @return array
     *
     * @throws QUI\Exception
     */
    public function search()
    {
        return $this->executeQueryParams($this->getQuery());
    }

    /**
     * Execute the search and return the order list for a grid control
     *
     * @return array
     * @throws QUI\Exception
     */
    public function searchForGrid()
    {
        $this->cache = [];

        // select display orders
        $orders = $this->executeQueryParams($this->getQuery());

        // count
        $count = $this->executeQueryParams($this->getQueryCount());
        $count = (int)$count[0]['count'];


        // total - calculation is without limit and paid_status
        $oldFiler = $this->filter;
        $oldLimit = $this->limit;

        $this->limit  = false;
        $this->filter = array_filter($this->filter, function ($filter) {
            return $filter['filter'] != 'paid_status';
        });

        $calc = $this->parseListForGrid($this->executeQueryParams($this->getQuery()));

        $this->filter = $oldFiler;
        $this->limit  = $oldLimit;


        // result
        $result = $this->parseListForGrid($orders);
        $Grid   = new QUI\Utils\Grid();


        return [
            'grid'  => $Grid->parseResult($result, $count),
            'total' => $calc
        ];
    }

    /**
     * @param bool $count - Use count select, or not
     * @return array
     */
    protected function getQuery($count = false)
    {
        $Order = Handler::getInstance();

        $table = $Order->table();
        $order = $this->order;

        // limit
        $limit = '';

        if ($this->limit && isset($this->limit[0]) && isset($this->limit[1])) {
            $start = $this->limit[0];
            $end   = $this->limit[1];
            $limit = " LIMIT {$start},{$end}";
        }

        if (empty($this->filter)) {
            if ($count) {
                return [
                    'query' => " SELECT COUNT(*)  AS count FROM {$table}",
                    'binds' => []
                ];
            }

            return [
                'query' => "
                    SELECT id
                    FROM {$table}
                    ORDER BY {$order}
                    {$limit}
                ",
                'binds' => []
            ];
        }

        $where = [];
        $binds = [];
        $fc    = 0;

        foreach ($this->filter as $filter) {
            $bind = ':filter'.$fc;

            switch ($filter['filter']) {
                case 'from':
                    $where[] = 'date >= '.$bind;
                    break;

                case 'to':
                    $where[] = 'date <= '.$bind;
                    break;

                default:
                    continue;
            }

            $binds[$bind] = [
                'value' => $filter['value'],
                'type'  => \PDO::PARAM_STR
            ];

            $fc++;
        }

        $whereQuery = 'WHERE '.implode(' AND ', $where);


        if ($count) {
            return [
                "query" => "
                    SELECT COUNT(*) AS count
                    FROM {$table}
                    {$whereQuery}
                ",
                'binds' => $binds
            ];
        }

        return [
            "query" => "
                SELECT id
                FROM {$table}
                {$whereQuery}
                ORDER BY {$order}
                {$limit}
            ",
            'binds' => $binds
        ];
    }

    /**
     * @return array
     */
    protected function getQueryCount()
    {
        return $this->getQuery(true);
    }

    /**
     * @param array $queryData
     * @return array
     * @throws QUI\Exception
     */
    protected function executeQueryParams($queryData = [])
    {
        $PDO   = QUI::getDataBase()->getPDO();
        $binds = $queryData['binds'];
        $query = $queryData['query'];

        $Statement = $PDO->prepare($query);

        foreach ($binds as $var => $bind) {
            $Statement->bindValue($var, $bind['value'], $bind['type']);
        }

        try {
            $Statement->execute();

            return $Statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            QUI\System\Log::writeRecursive($query);
            QUI\System\Log::writeRecursive($binds);
            throw new QUI\Exception('Something went wrong');
        }
    }

    /**
     * @param array $data
     * @return array
     *
     * @throws QUI\ERP\Order\Exception
     */
    protected function parseListForGrid($data)
    {
        $Orders   = Handler::getInstance();
        $Locale   = QUI::getLocale();
        $Payments = Payments::getInstance();
        $Currency = QUI\ERP\Defaults::getCurrency();

        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $DateFormatter = new \IntlDateFormatter(
            $localeCode[0],
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE
        );

        // helper
        $needleFields = [
            'invoice_id',
            'customer_id',
            'customer_name',
            'comments',
            'c_user',
            'c_username',
            'c_date',
            'display_nettosum',
            'display_vatsum',
            'display_sum',
            'hash',
            'id',
            'isbrutto',
            'nettosum',
            'order_id',
            'orderdate',
            'paid_status',
            'processing',
            'payment_data',
            'payment_method',
            'payment_title',
            'processing_status',
            'taxId',
            'euVatId'
        ];

        $fillFields = function (&$data) use ($needleFields) {
            foreach ($needleFields as $field) {
                if (!isset($data[$field])) {
                    $data[$field] = Handler::EMPTY_VALUE;
                }
            }
        };

        $result = [];

        foreach ($data as $entry) {
            if (isset($this->cache[$entry['id']])) {
                $result[] = $this->cache[$entry['id']];
                continue;
            }

            $Order     = $Orders->get($entry['id']);
            $Customer  = $Order->getCustomer();
            $orderData = $entry;

            $orderData['hash'] = $Order->getHash();


            if (empty($orderData['customer_id'])) {
                $orderData['customer_id'] = $Customer->getId();

                if (!$orderData['customer_id']) {
                    $orderData['customer_id'] = Handler::EMPTY_VALUE;
                } else {
                    $orderData['customer_name'] = $Customer->getName();
                }
            }

            if (empty($orderData['c_date'])) {
                $orderData['c_date'] = $DateFormatter->format(
                    strtotime($Order->getCreateDate())
                );
            }

            // payment
            $Payment = $Order->getPayment();

            if ($Payment) {
                $orderData['payment_title'] = $Payment->getTitle($Locale);
            }

            // articles
            $calculations = $Order->getArticles()->getCalculations();

            $vatSum = array_map(function ($data) {
                if (!isset($data['sum'])) {
                    return 0;
                }

                return $data['sum'];
            }, $calculations['vatArray']);

            if ($Customer->getAttribute('quiqqer.erp.taxId')) {
                $orderData['taxId'] = $Customer->getAttribute('quiqqer.erp.taxId');
            }

            if ($Customer->getAttribute('quiqqer.erp.euVatId')) {
                $orderData['euVatId'] = $Customer->getAttribute('quiqqer.erp.euVatId');
            }


            // display
            $orderData['display_nettosum'] = $Currency->format($calculations['nettoSum']);
            $orderData['display_sum']      = $Currency->format($calculations['sum']);
            $orderData['display_subsum']   = $Currency->format($calculations['subSum']);
            $orderData['display_vatsum']   = $Currency->format(array_sum($vatSum));

            $fillFields($orderData);

            $result[] = $orderData;
        }


        return $result;
    }
}
