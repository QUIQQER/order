<?php

/**
 * This file contains QUI\ERP\Order\Search
 */

namespace QUI\ERP\Order;

use Doctrine\DBAL\Query\QueryBuilder;
use QUI;
use QUI\Exception;
use QUI\Utils\Doctrine;
use QUI\Utils\Singleton;

use function array_filter;
use function array_flip;
use function array_map;
use function array_sum;
use function explode;
use function implode;
use function is_array;
use function is_numeric;
use function json_encode;
use function strtotime;
use function trim;

/**
 * Class Search
 * @package QUI\ERP\Order
 */
class Search extends Singleton
{
    /**
     * @var array<int, array{filter: string, value: mixed}>
     */
    protected array $filter = [];

    /**
     * search value
     *
     * @var null|string
     */
    protected ?string $search = null;

    /**
     * @var array{int, int}|bool
     */
    protected array | bool $limit = [0, 20];

    /**
     * @var string
     */
    protected string $order = 'id DESC';

    /**
     * @var list<string>
     */
    protected array $allowedFilters = [
        'from',
        'to',
        'status'
    ];

    /**
     * @var array<string, mixed>
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
     * @param array<int, mixed>|string|null $value
     */
    public function setFilter(string $filter, array | string | null $value): void
    {
        if ($value === null) {
            return;
        }

        if ($filter === 'search') {
            $this->search = $value;

            return;
        }

        if ($filter === 'currency') {
            if (empty($value)) {
                $this->currency = QUI\ERP\Currency\Handler::getDefaultCurrency()->getCode();

                return;
            }

            $allowed = QUI\ERP\Currency\Handler::getAllowedCurrencies();

            $allowed = array_map(function ($Currency) {
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
    public function clearFilter(): void
    {
        $this->filter = [];
        $this->search = null;
    }

    /**
     * Set the limit
     *
     * @param int|string $from
     * @param int|string $to
     */
    public function limit(int | string $from, int | string $to): void
    {
        $this->limit = [(int)$from, (int)$to];
    }

    /**
     * Set the order
     *
     * @param string $order
     */
    public function order($order): void
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
     * @return list<array<string, mixed>>
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
     * @return array<string, mixed>
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
        $this->filter = array_filter($this->filter, function ($filter) {
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
            } catch (QUI\Exception) {
            }
        }

        // result
        $page = 1;

        if (!empty($this->limit[0]) && !empty($this->limit[1])) {
            $page = ($this->limit[0] / $this->limit[1]) + 1;
        }

        $result = $this->parseListForGrid($orders);
        $Grid = new QUI\Utils\Grid();
        $Grid->setAttribute('page', $page);

        return [
            'grid' => $Grid->parseResult($result, $count),
            'total' => QUI\ERP\Accounting\Calc::calculateTotal($calc, $Currency)
        ];
    }

    /**
     * @param bool $count - Use count select, or not
     * @return QueryBuilder
     * @throws Exception
     */
    protected function getQuery(bool $count = false): QueryBuilder
    {
        $Connection = QUI::getDataBaseConnection();
        $QueryBuilder = $Connection->createQueryBuilder();
        $table = Doctrine::quoteIdentifier(Handler::getInstance()->table());

        if (empty($this->filter) && empty($this->search)) {
            if ($count) {
                return $QueryBuilder
                    ->select('COUNT(*) AS count')
                    ->from($table);
            }

            $QueryBuilder
                ->select(Doctrine::quoteIdentifier('id'))
                ->from($table);

            $this->applyOrderAndLimit($QueryBuilder);

            return $QueryBuilder;
        }

        if ($count) {
            $QueryBuilder->select('COUNT(*) AS count');
        } else {
            $QueryBuilder->select(Doctrine::quoteIdentifier('id'));
        }

        $QueryBuilder->from($table);

        // currency
        $DefaultCurrency = QUI\ERP\Currency\Handler::getDefaultCurrency();

        if (empty($this->currency)) {
            $this->currency = $DefaultCurrency->getCode();
        }

        // fallback for old orders
        if ($DefaultCurrency->getCode() === $this->currency) {
            $QueryBuilder->andWhere($QueryBuilder->expr()->or(
                Doctrine::quoteIdentifier('currency') . ' = :currency',
                Doctrine::quoteIdentifier('currency') . ' = :emptyCurrency',
                Doctrine::quoteIdentifier('currency') . ' IS NULL'
            ));
            $QueryBuilder->setParameter('emptyCurrency', '');
        } else {
            $QueryBuilder->andWhere(Doctrine::quoteIdentifier('currency') . ' = :currency');
        }

        $QueryBuilder->setParameter('currency', $this->currency);

        // filter
        foreach ($this->filter as $index => $filter) {
            $parameter = 'filter' . $index;

            if ($filter['filter'] === 'status') {
                $QueryBuilder
                    ->andWhere(Doctrine::quoteIdentifier('status') . ' = :' . $parameter)
                    ->setParameter($parameter, (int)$filter['value']);
                continue;
            }

            switch ($filter['filter']) {
                case 'from':
                    $QueryBuilder->andWhere(Doctrine::quoteIdentifier('c_date') . ' >= :' . $parameter);
                    break;

                case 'to':
                    $QueryBuilder->andWhere(Doctrine::quoteIdentifier('c_date') . ' <= :' . $parameter);
                    break;

                default:
                    continue 2;
            }

            $QueryBuilder->setParameter($parameter, $filter['value']);
        }

        if (!empty($this->search)) {
            $Platform = $Connection->getDatabasePlatform();
            $searchExpressions = [];
            $searchFields = [
                'id',
                'id_prefix',
                'id_str',
                'order_process_id',
                'parent_order',
                'invoice_id',
                'temporary_invoice_id',
                'customerId',
                'customer',
                'addressInvoice',
                'addressDelivery',
                'data',
                'payment_time',
                'payment_address',
                'paid_status',
                'paid_date',
                'paid_data',
                'hash',
                'c_date',
                'c_user'
            ];

            foreach ($searchFields as $field) {
                $searchExpressions[] = $QueryBuilder->expr()->like(
                    $Platform->getConcatExpression(':emptySearch', Doctrine::quoteIdentifier($field)),
                    ':search'
                );
            }

            $QueryBuilder
                ->andWhere($QueryBuilder->expr()->or(...$searchExpressions))
                ->setParameter('emptySearch', '')
                ->setParameter('search', '%' . $this->search . '%');
        }

        if (!$count) {
            $this->applyOrderAndLimit($QueryBuilder);
        }

        return $QueryBuilder;
    }

    /**
     * @return QueryBuilder
     * @throws Exception
     */
    protected function getQueryCount(): QueryBuilder
    {
        return $this->getQuery(true);
    }

    /**
     * @param QueryBuilder $QueryBuilder
     * @return array<int, array<string, mixed>>
     * @throws QUI\Exception
     */
    protected function executeQueryParams(QueryBuilder $QueryBuilder): array
    {
        try {
            return $QueryBuilder->executeQuery()->fetchAllAssociative();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            QUI\System\Log::writeRecursive($QueryBuilder->getSQL());
            QUI\System\Log::writeRecursive($QueryBuilder->getParameters());
            throw new QUI\Exception('Something went wrong');
        }
    }

    private function applyOrderAndLimit(QueryBuilder $QueryBuilder): void
    {
        [$orderField, $orderDirection] = array_pad(explode(' ', $this->order, 2), 2, 'ASC');
        $QueryBuilder->orderBy(Doctrine::quoteIdentifier($orderField), $orderDirection);

        if ($this->limit && isset($this->limit[0], $this->limit[1])) {
            $QueryBuilder
                ->setFirstResult($this->limit[0])
                ->setMaxResults($this->limit[1]);
        }
    }

    /**
     * @param list<array<string, mixed>> $data
     * @return list<array<string, mixed>>
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

                $fillFields($entry);
                continue;
            }

            $Customer = $Order->getCustomer();
            $orderData = $entry;

            $orderData['id'] = (int)$orderData['id'];
            $orderData['hash'] = $Order->getUUID();
            $orderData['uuid'] = $Order->getUUID();
            $orderData['globalProcessId'] = $Order->getGlobalProcessId();
            $orderData['prefixed-id'] = $Order->getPrefixedNumber();
            $orderData['customer_no'] = '';

            // customer data
            if (empty($orderData['customer_id'])) {
                $orderData['customer_id'] = $Customer->getUUID();
                $orderData['customer_no'] = $Customer->getCustomerNo();

                if (!$orderData['customer_id']) {
                    $orderData['customer_id'] = Handler::EMPTY_VALUE;
                }

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

            if (empty($orderData['c_date'])) {
                $orderData['c_date'] = $Locale->formatDate(
                    strtotime($Order->getCreateDate()),
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

                if (class_exists('QUI\ERP\Shipping\ShippingStatus\Status')) {
                    $ShippingStatus = $Order->getShippingStatus();

                    if ($ShippingStatus) {
                        $orderData['shipping_status_id'] = $ShippingStatus->getId();
                        $orderData['shipping_status_title'] = $ShippingStatus->getTitle();
                        $orderData['shipping_status_color'] = $ShippingStatus->getColor();
                    }
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
                'quiqqer/order',
                'payment.status.' . $Order->getAttribute('paid_status')
            );

            // invoice
            if ($Order->hasInvoice()) {
                try {
                    $orderData['invoice_id'] = $Order->getInvoice()->getUUID();
                    $orderData['invoice_status'] = $Order->getInvoice()->getAttribute('status');
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::writeDebugException($Exception);
                }
            }

            // currency data
            $orderData['currency_data'] = json_encode($Currency->toArray());


            // calculation
            $transactions = $Transactions->getTransactionsByHash($Order->getUUID());

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
     * @return list<string>
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
