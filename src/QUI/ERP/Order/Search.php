<?php

/**
 * This file contains QUI\ERP\Order\Search
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\Utils\Singleton;

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
     * search value
     *
     * @var null
     */
    protected $search = null;

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
        if ($filter === 'search') {
            $this->search = $value;

            return;
        }

        $keys = \array_flip($this->allowedFilters);

        if (!isset($keys[$filter]) && $filter !== 'from' && $filter !== 'to') {
            return;
        }

        if (!\is_array($value)) {
            $value = [$value];
        }

        foreach ($value as $val) {
            if ($val === '') {
                continue;
            }

            if ($filter === 'from' && \is_numeric($val)) {
                $val = date('Y-m-d 00:00:00', $val);
            }

            if ($filter === 'to' && \is_numeric($val)) {
                $val = date('Y-m-d 23:59:59', $val);
            }

            $this->filter[] = [
                'filter' => $filter,
                'value'  => $val
            ];
        }
    }

    /**
     * Clear all filters
     */
    public function clearFilter()
    {
        $this->filter = [];
        $this->search = null;
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
        $allowed = [];

        foreach ($this->getAllowedFields() as $field) {
            $allowed[] = $field;
            $allowed[] = $field.' ASC';
            $allowed[] = $field.' asc';
            $allowed[] = $field.' DESC';
            $allowed[] = $field.' desc';
        }

        $order   = \trim($order);
        $allowed = \array_flip($allowed);

        if (isset($allowed[$order])) {
            $this->order = $order;
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
        $this->filter = \array_filter($this->filter, function ($filter) {
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
            'total' => QUI\ERP\Accounting\Calc::calculateTotal($calc)
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

        if (empty($this->filter) && empty($this->search)) {
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
                    $where[] = 'c_date >= '.$bind;
                    break;

                case 'to':
                    $where[] = 'c_date <= '.$bind;
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

        if (!empty($this->search)) {
            $where[] = '(
                id LIKE :search OR
                id_prefix LIKE :search OR
                id_str LIKE :search OR
                order_process_id LIKE :search OR
                parent_order LIKE :search OR
                invoice_id LIKE :search OR
                temporary_invoice_id LIKE :search OR
                customerId LIKE :search OR
                customer LIKE :search OR
                addressInvoice LIKE :search OR
                addressDelivery LIKE :search OR
                data LIKE :search OR
                payment_time LIKE :search OR
                payment_address LIKE :search OR
                paid_status LIKE :search OR
                paid_date LIKE :search OR
                paid_data LIKE :search OR
                hash LIKE :search OR
                c_date LIKE :search OR
                c_user LIKE :search
            )';

            $binds['search'] = [
                'value' => '%'.$this->search.'%',
                'type'  => \PDO::PARAM_STR
            ];
        }

        $whereQuery = 'WHERE '.\implode(' AND ', $where);

        if (!\count($where)) {
            $whereQuery = '';
        }


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
     */
    protected function parseListForGrid($data)
    {
        $Orders       = Handler::getInstance();
        $Locale       = QUI::getLocale();
        $Transactions = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();

        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $DateFormatter = new \IntlDateFormatter(
            $localeCode[0],
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::MEDIUM
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
            'status',
            'status_id',
            'status_title',
            'status_color',
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

            try {
                $Order = $Orders->get($entry['id']);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);

                $result[] = $fillFields($entry);
                continue;
            }

            $Customer  = $Order->getCustomer();
            $orderData = $entry;

            $orderData['id']          = (int)$orderData['id'];
            $orderData['hash']        = $Order->getHash();
            $orderData['prefixed-id'] = $Order->getPrefixedId();

            // customer data
            if (empty($orderData['customer_id'])) {
                $orderData['customer_id'] = $Customer->getId();

                if (!$orderData['customer_id']) {
                    $orderData['customer_id'] = Handler::EMPTY_VALUE;
                } else {
                    $orderData['customer_name'] = $Customer->getName();

                    $Address = $Order->getInvoiceAddress();

                    if (empty(\trim($orderData['customer_name']))) {
                        $orderData['customer_name'] = $Address->getAttribute('firstname');
                        $orderData['customer_name'] .= ' ';
                        $orderData['customer_name'] .= $Address->getAttribute('lastname');

                        $orderData['customer_name'] = \trim($orderData['customer_name']);
                    }

                    if ($Address) {
                        $address = $Address->getAttributes();

                        if (!empty($address['company'])) {
                            $orderData['customer_name'] = \trim($orderData['customer_name']);

                            if (!empty($orderData['customer_name'])) {
                                $orderData['customer_name'] = ' ('.$orderData['customer_name'].')';
                            }

                            $orderData['customer_name'] = $address['company'].$orderData['customer_name'];
                        }
                    }
                }
            }

            if (empty($orderData['c_date'])) {
                $orderData['c_date'] = $DateFormatter->format(
                    \strtotime($Order->getCreateDate())
                );
            }

            // processing status
            $orderData['status_id']    = Handler::EMPTY_VALUE;
            $orderData['status_title'] = Handler::EMPTY_VALUE;
            $orderData['status_color'] = '';

            try {
                $ProcessingStatus = $Order->getProcessingStatus();

                $orderData['status_id']    = $ProcessingStatus->getId();
                $orderData['status_title'] = $ProcessingStatus->getTitle();
                $orderData['status_color'] = $ProcessingStatus->getColor();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }

            // payment
            $Payment  = $Order->getPayment();
            $Currency = $Order->getCurrency();

            if ($Payment) {
                $orderData['payment_title'] = $Payment->getTitle($Locale);
            }

            // articles
            try {
                $calculations = $Order->getArticles()->getCalculations();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
                continue;
            }

            if ($Customer->getAttribute('quiqqer.erp.taxId')) {
                $orderData['taxId'] = $Customer->getAttribute('quiqqer.erp.taxId');
            }

            if ($Customer->getAttribute('quiqqer.erp.euVatId')) {
                $orderData['euVatId'] = $Customer->getAttribute('quiqqer.erp.euVatId');
            }

            // payment
            $orderData['paid_status_display'] = $Locale->get(
                'quiqqer/invoice',
                'payment.status.'.$Order->getAttribute('paid_status')
            );

            // invoice
            if ($Order->hasInvoice()) {
                try {
                    $orderData['invoice_id']     = $Order->getInvoice()->getId();
                    $orderData['invoice_status'] = $Order->getInvoice()->getAttribute('status');
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::writeDebugException($Exception);
                }
            }

            // currency data
            $orderData['currency_data'] = \json_encode($Currency->toArray());


            // calculation
            $transactions = $Transactions->getTransactionsByHash($Order->getHash());

            $paid = \array_map(function ($Transaction) {
                /* @var $Transaction QUI\ERP\Accounting\Payments\Transactions\Transaction */
                if ($Transaction->isPending()) {
                    return 0;
                }

                return $Transaction->getAmount();
            }, $transactions);

            $paid = \array_sum($paid);

            $orderData['calculated_nettosum'] = $calculations['nettoSum'];
            $orderData['calculated_sum']      = $calculations['sum'];
            $orderData['calculated_subsum']   = $calculations['subSum'];
            $orderData['calculated_paid']     = $paid;
            $orderData['calculated_toPay']    = $calculations['sum'] - $paid;


            // vat information
            $vatArray = $calculations['vatArray'];

            $vat = \array_map(function ($data) use ($Currency) {
                return $data['text'].': '.$Currency->format($data['sum']);
            }, $vatArray);

            $vatSum = \array_map(function ($data) {
                return $data['sum'];
            }, $vatArray);

            $orderData['vat']               = \implode('; ', $vat);
            $orderData['display_vatsum']    = $Currency->format(\array_sum($vatSum));
            $orderData['calculated_vat']    = $vatSum;
            $orderData['calculated_vatsum'] = \array_sum($vatSum);


            // display
            $orderData['display_nettosum'] = $Currency->format($calculations['nettoSum']);
            $orderData['display_sum']      = $Currency->format($calculations['sum']);
            $orderData['display_subsum']   = $Currency->format($calculations['subSum']);
            $orderData['display_vatsum']   = $Currency->format(\array_sum($vatSum));

            $fillFields($orderData);

            $result[] = $orderData;
        }

        return $result;
    }


    /**
     * @return array
     */
    protected function getAllowedFields()
    {
        return [
            'id',
            'id_prefix',
            'order_process_id',

            'parent_order',

            'invoice_id',
            'temporary_invoice_id',
            'status',

            'customerId',
            'customer',
            'addressInvoice',
            'addressDelivery',

            'articles',
            'data',

            'payment_id',
            'payment_method',
            'payment_data',
            'payment_time',
            'payment_address',
            'paid_status',
            'paid_date',
            'paid_data',
            'successful',

            'history',
            'comments',

            'hash',
            'c_date',
            'c_user',

        ];
    }
}
