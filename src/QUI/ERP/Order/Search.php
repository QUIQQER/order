<?php

/**
 * This file contains QUI\ERP\Order\Search
 */

namespace QUI\ERP\Order;

use PDO;
use QUI;
use QUI\Utils\Singleton;

use function array_flip;
use function array_map;
use function array_sum;
use function implode;
use function is_array;
use function is_numeric;
use function trim;

/**
 * Class Search
 * @package QUI\ERP\Order
 */
class Search extends Singleton
{
    /**
     * @var array
     */
    protected array $filter = [];

    /**
     * search value
     *
     * @var null|string
     */
    protected ?string $search = null;

    /**
     * @var array|bool
     */
    protected $limit = [0, 20];

    /**
     * @var string
     */
    protected string $order = 'id DESC';

    /**
     * @var array
     */
    protected array $allowedFilters = [
        'from',
        'to',
        'status'
    ];

    /**
     * @var array
     */
    protected array $cache = [];

    /**
     * currency of the searched orders
     *
     * @var string
     */
    protected string $currency = '';

    /**
     * Set a filter
     *
     * @param string $filter
     * @param string|array $value
     */
    public function setFilter(string $filter, $value)
    {
        if ($filter === 'search') {
            $this->search = $value;

            return;
        }

        if ($filter === 'currency') {
            if (empty($value)) {
                $this->currency = QUI\ERP\Currency\Handler::getDefaultCurrency()->getCode();

                return;
            }

            try {
                $allowed = QUI\ERP\Currency\Handler::getAllowedCurrencies();
            } catch (QUI\Exception $Exception) {
                return;
            }

            $allowed = array_map(function ($Currency) {
                /* @var $Currency QUI\ERP\Currency\Currency */
                return $Currency->getCode();
            }, $allowed);

            $allowed = array_flip($allowed);

            if (isset($allowed[$value])) {
                $this->currency = $value;
            }

            return;
        }

        $keys = array_flip($this->allowedFilters);

        if (!isset($keys[$filter]) && $filter !== 'from' && $filter !== 'to') {
            return;
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        foreach ($value as $val) {
            if ($val === '') {
                continue;
            }

            if ($filter === 'from' && is_numeric($val)) {
                $val = date('Y-m-d 00:00:00', $val);
            }

            if ($filter === 'to' && is_numeric($val)) {
                $val = date('Y-m-d 23:59:59', $val);
            }

            $this->filter[] = [
                'filter' => $filter,
                'value' => $val
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
     * @param string|int $from
     * @param string|int $to
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
            $allowed[] = $field . ' ASC';
            $allowed[] = $field . ' asc';
            $allowed[] = $field . ' DESC';
            $allowed[] = $field . ' desc';
        }

        $order = trim($order);
        $allowed = array_flip($allowed);

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
    public function search(): array
    {
        return $this->executeQueryParams($this->getQuery());
    }

    /**
     * Execute the search and return the order list for a grid control
     *
     * @return array
     * @throws QUI\Exception
     */
    public function searchForGrid(): array
    {
        $this->cache = [];

        // select display orders
        $query = $this->getQuery();
        $queryCount = $this->getQueryCount();

        $orders = $this->executeQueryParams($query);

        // count
        $count = $this->executeQueryParams($queryCount);
        $count = (int)$count[0]['count'];

        // total - calculation is without limit and paid_status
        $oldFiler = $this->filter;
        $oldLimit = $this->limit;

        $this->limit = false;
        $this->filter = \array_filter($this->filter, function ($filter) {
            return $filter['filter'] != 'paid_status';
        });

        $calc = $this->parseListForGrid($this->executeQueryParams($this->getQuery()));

        $this->filter = $oldFiler;
        $this->limit = $oldLimit;

        // currency
        $Currency = null;

        if (!empty($this->currency)) {
            try {
                $Currency = QUI\ERP\Currency\Handler::getCurrency($this->currency);
            } catch (QUI\Exception $Exception) {
            }
        }

        // result
        $result = $this->parseListForGrid($orders);
        $Grid = new QUI\Utils\Grid();

        return [
            'grid' => $Grid->parseResult($result, $count),
            'total' => QUI\ERP\Accounting\Calc::calculateTotal($calc, $Currency)
        ];
    }

    /**
     * @param bool $count - Use count select, or not
     * @return array
     */
    protected function getQuery(bool $count = false)
    {
        $Order = Handler::getInstance();

        $table = $Order->table();
        $order = $this->order;

        // limit
        $limit = '';

        if ($this->limit && isset($this->limit[0]) && isset($this->limit[1])) {
            $start = $this->limit[0];
            $end = $this->limit[1];
            $limit = " LIMIT $start,$end";
        }

        if (empty($this->filter) && empty($this->search)) {
            if ($count) {
                return [
                    'query' => " SELECT COUNT(*)  AS count FROM $table",
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

        // filter start
        $where = [];
        $binds = [];
        $fc = 0;

        // currency
        $DefaultCurrency = QUI\ERP\Currency\Handler::getDefaultCurrency();

        if (empty($this->currency)) {
            $this->currency = $DefaultCurrency->getCode();
        }

        // fallback for old orders
        if ($DefaultCurrency->getCode() === $this->currency) {
            $where[] = "(currency = :currency OR currency = '' OR currency IS NULL)";
        } else {
            $where[] = 'currency = :currency';
        }

        $binds[':currency'] = [
            'value' => $this->currency,
            'type' => PDO::PARAM_STR
        ];

        // filter
        foreach ($this->filter as $filter) {
            $bind = ':filter' . $fc;

            if ($filter['filter'] === 'status') {
                $where[] = 'status = ' . $bind;
                $binds[$bind] = [
                    'value' => (int)$filter['value'],
                    'type' => PDO::PARAM_INT
                ];

                $fc++;
                continue;
            }

            switch ($filter['filter']) {
                case 'from':
                    $where[] = 'c_date >= ' . $bind;
                    break;

                case 'to':
                    $where[] = 'c_date <= ' . $bind;
                    break;

                default:
                    continue 2;
            }

            $binds[$bind] = [
                'value' => $filter['value'],
                'type' => PDO::PARAM_STR
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
                'value' => '%' . $this->search . '%',
                'type' => PDO::PARAM_STR
            ];
        }

        $whereQuery = 'WHERE ' . implode(' AND ', $where);

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
    protected function getQueryCount(): array
    {
        return $this->getQuery(true);
    }

    /**
     * @param array $queryData
     * @return array
     * @throws QUI\Exception
     */
    protected function executeQueryParams(array $queryData = []): array
    {
        $PDO = QUI::getDataBase()->getPDO();
        $binds = $queryData['binds'];
        $query = $queryData['query'];

        $Statement = $PDO->prepare($query);

        foreach ($binds as $var => $bind) {
            $Statement->bindValue($var, $bind['value'], $bind['type']);
        }

        try {
            $Statement->execute();

            return $Statement->fetchAll(PDO::FETCH_ASSOC);
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
    protected function parseListForGrid(array $data): array
    {
        $Orders = Handler::getInstance();
        $Locale = QUI::getLocale();
        $Transactions = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();
        $shippingIsInstalled = QUI::getPackageManager()->isInstalled('quiqqer/shipping');
        $defaultTimeFormat = QUI\ERP\Defaults::getTimestampFormat();

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
            'euVatId',
            'shipping_status',
            'shipping_status_id',
            'shipping_status_title',
            'shipping_status_color',
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

            $Customer = $Order->getCustomer();
            $orderData = $entry;

            $orderData['id'] = (int)$orderData['id'];
            $orderData['hash'] = $Order->getHash();
            $orderData['prefixed-id'] = $Order->getPrefixedId();

            // customer data
            if (empty($orderData['customer_id'])) {
                $orderData['customer_id'] = $Customer->getId();

                if (!$orderData['customer_id']) {
                    $orderData['customer_id'] = Handler::EMPTY_VALUE;
                } else {
                    $orderData['customer_name'] = $Customer->getName();

                    if (empty($orderData['customer_name'])) {
                        $orderData['customer_name'] = $Customer->getAttribute('email');
                    }

                    $Address = $Order->getInvoiceAddress();

                    if (empty(trim($orderData['customer_name']))) {
                        $orderData['customer_name'] = $Address->getAttribute('firstname');
                        $orderData['customer_name'] .= ' ';
                        $orderData['customer_name'] .= $Address->getAttribute('lastname');

                        $orderData['customer_name'] = trim($orderData['customer_name']);
                    }

                    $address = $Address->getAttributes();

                    if (!empty($address['company'])) {
                        $orderData['customer_name'] = trim($orderData['customer_name']);

                        if (!empty($orderData['customer_name'])) {
                            $orderData['customer_name'] = ' (' . $orderData['customer_name'] . ')';
                        }

                        $orderData['customer_name'] = $address['company'] . $orderData['customer_name'];
                    }
                }
            }

            if (empty($orderData['c_date'])) {
                $orderData['c_date'] = $Locale->formatDate(
                    \strtotime($Order->getCreateDate()),
                    $defaultTimeFormat
                );
            }

            // processing status
            $orderData['status_id'] = Handler::EMPTY_VALUE;
            $orderData['status_title'] = Handler::EMPTY_VALUE;
            $orderData['status_color'] = '';

            $ProcessingStatus = $Order->getProcessingStatus();

            if ($ProcessingStatus) {
                $orderData['status_id'] = $ProcessingStatus->getId();
                $orderData['status_title'] = $ProcessingStatus->getTitle();
                $orderData['status_color'] = $ProcessingStatus->getColor();
            }

            if ($shippingIsInstalled) {
                $orderData['shipping_status_id'] = Handler::EMPTY_VALUE;
                $orderData['shipping_status_title'] = Handler::EMPTY_VALUE;
                $orderData['shipping_status_color'] = '';

                $ShippingStatus = $Order->getShippingStatus();

                if ($ShippingStatus) {
                    $orderData['shipping_status_id'] = $ShippingStatus->getId();
                    $orderData['shipping_status_title'] = $ShippingStatus->getTitle();
                    $orderData['shipping_status_color'] = $ShippingStatus->getColor();
                }
            }

            // payment
            $Payment = $Order->getPayment();
            $Currency = $Order->getCurrency();

            $orderData['paymentId'] = false;

            if ($Payment) {
                $orderData['payment_title'] = $Payment->getTitle($Locale);
                $orderData['paymentId'] = $Payment->getId();
            }

            // articles
            $calculations = $Order->getArticles()->getCalculations();

            if ($Customer->getAttribute('quiqqer.erp.taxId')) {
                $orderData['taxId'] = $Customer->getAttribute('quiqqer.erp.taxId');
            }

            if ($Customer->getAttribute('quiqqer.erp.euVatId')) {
                $orderData['euVatId'] = $Customer->getAttribute('quiqqer.erp.euVatId');
            }

            // payment
            $orderData['paid_status_display'] = $Locale->get(
                'quiqqer/invoice',
                'payment.status.' . $Order->getAttribute('paid_status')
            );

            // invoice
            if ($Order->hasInvoice()) {
                try {
                    $orderData['invoice_id'] = $Order->getInvoice()->getId();
                    $orderData['invoice_status'] = $Order->getInvoice()->getAttribute('status');
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::writeDebugException($Exception);
                }
            }

            // currency data
            $orderData['currency_data'] = \json_encode($Currency->toArray());


            // calculation
            $transactions = $Transactions->getTransactionsByHash($Order->getHash());

            $paid = array_map(function ($Transaction) {
                if ($Transaction->isPending()) {
                    return 0;
                }

                return $Transaction->getAmount();
            }, $transactions);

            $paid = array_sum($paid);

            $orderData['calculated_nettosum'] = $calculations['nettoSum'];
            $orderData['calculated_sum'] = $calculations['sum'];
            $orderData['calculated_subsum'] = $calculations['subSum'];
            $orderData['calculated_paid'] = $paid;
            $orderData['calculated_toPay'] = $calculations['sum'] - $paid;


            // vat information
            $vatArray = $calculations['vatArray'];

            $vat = array_map(function ($data) use ($Currency) {
                return $data['text'] . ': ' . $Currency->format($data['sum']);
            }, $vatArray);

            $vatSum = array_map(function ($data) {
                return $data['sum'];
            }, $vatArray);

            $orderData['vat'] = implode('; ', $vat);
            $orderData['display_vatsum'] = $Currency->format(array_sum($vatSum));
            $orderData['calculated_vat'] = $vatSum;
            $orderData['calculated_vatsum'] = array_sum($vatSum);


            // display
            $orderData['display_nettosum'] = $Currency->format($calculations['nettoSum']);
            $orderData['display_sum'] = $Currency->format($calculations['sum']);
            $orderData['display_subsum'] = $Currency->format($calculations['subSum']);
            $orderData['display_vatsum'] = $Currency->format(array_sum($vatSum));

            $fillFields($orderData);

            $result[] = $orderData;
        }

        return $result;
    }


    /**
     * @return array
     */
    protected function getAllowedFields(): array
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
            'c_user'
        ];
    }
}
