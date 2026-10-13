<?php

namespace QUI\ERP\Order;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI;
use QUI\AI\MCP\ProviderInterface;
use QUI\AI\MCP\Server;
use QUI\AI\MCP\ToolHelper;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Order\Controls\OrderProcess\CustomerData;
use QUI\Permissions\Permission;
use QUI\Utils\Doctrine;
use Throwable;

/**
 * Optional MCP access to orders. Reorders remain drafts for review before checkout.
 */
class McpProvider implements ProviderInterface
{
    private const PERMISSION = 'quiqqer.order.mcp';

    public function register(Builder $serverBuilder): void
    {
        try {
            $this->checkPermission();
        } catch (Throwable) {
            return;
        }

        $identifier = [
            'type' => 'string',
            'minLength' => 1,
            'description' => 'Numeric id, prefixed order number, or UUID/hash. Set draft=true for a draft.'
        ];
        $draft = [
            'type' => 'boolean',
            'default' => false
        ];
        $dataSchema = $this->getDataSchema();

        $serverBuilder->addTool(
            function (string $orderId, bool $draft = false, bool $includeArticles = true): array | CallToolResult {
                try {
                    $this->checkPermission();

                    return $this->parseOrder($this->getOrder($orderId, $draft), $includeArticles);
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'order_get',
            description: 'Reads an order or draft, including addresses, statuses, totals and optionally articles.',
            inputSchema: $this->objectSchema([
                'orderId' => $identifier,
                'draft' => $draft,
                'includeArticles' => [
                    'type' => 'boolean',
                    'default' => true
                ]
            ], ['orderId'])
        );

        $serverBuilder->addTool(
            function (
                string $query = '',
                int $limit = 20,
                int $offset = 0,
                bool $draft = false,
                string $customerId = '',
                ?int $status = null,
                string $order = 'id DESC'
            ): array | CallToolResult {
                try {
                    $this->checkPermission();

                    return $this->searchOrders($query, $limit, $offset, $draft, $customerId, $status, $order);
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'order_search',
            description: 'Searches orders or drafts by number/hash, with customer and processing status filters. '
                . 'Returns compact summaries and the total matching count.',
            inputSchema: $this->objectSchema([
                'query' => ['type' => 'string', 'default' => ''],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                'draft' => $draft,
                'customerId' => ['type' => 'string', 'default' => ''],
                'status' => ['type' => 'integer', 'description' => 'Processing status id.'],
                'order' => [
                    'type' => 'string',
                    'enum' => ['id ASC', 'id DESC', 'c_date ASC', 'c_date DESC'],
                    'default' => 'id DESC'
                ]
            ])
        );

        $serverBuilder->addTool(
            function (array $data = []): array | CallToolResult {
                try {
                    $this->checkPermission('quiqqer.order.create');
                    $this->validateObject($data, $this->getDataSchema());

                    return QUI::getDataBaseConnection()->transactional(function () use ($data): array {
                        $Draft = $this->createDraft();
                        $this->applyData($Draft, $data);
                        $Draft->save(Server::getRequestUser());

                        return $this->parseOrder($Draft, true);
                    });
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'order_draft_create',
            description: 'Prepares an ERP order draft with optional initial data. '
                . 'Article prices are supplied draft values and must be reviewed before creating the order.',
            inputSchema: $this->objectSchema(['data' => $dataSchema])
        );

        $serverBuilder->addTool(
            function (string $orderId, array $data): array | CallToolResult {
                try {
                    $this->checkPermission('quiqqer.order.update');
                    $this->validateObject($data, $this->getDataSchema());
                    $Draft = $this->checkDraft($this->getOrder($orderId, true));

                    return $this->saveData($Draft, $data);
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'order_draft_update',
            description: 'Updates an unsubmitted draft owned by the request user (or accessible to a superuser). '
                . 'The articles field replaces all draft articles. An empty delivery address removes it.',
            inputSchema: $this->objectSchema([
                'orderId' => $identifier,
                'data' => $dataSchema
            ], ['orderId', 'data'])
        );

        $serverBuilder->addTool(
            function (string $orderId): array | CallToolResult {
                try {
                    $this->checkPermission('quiqqer.order.update');

                    return QUI::getDataBaseConnection()->transactional(function () use ($orderId): array {
                        $Draft = $this->checkDraft($this->getOrder($orderId, true));
                        $this->lockDraft($Draft->getUUID());
                        $Draft = $this->checkDraft($this->getOrder($Draft->getUUID(), true));
                        $quantities = $this->getArticleQuantities($Draft);
                        $Draft->recalculate();
                        $this->checkArticleQuantities($Draft, $quantities);
                        $result = $this->parseOrder($Draft, true);
                        $errors = $this->getSubmissionErrors($Draft);
                        $result['readyToSubmit'] = $errors === [];
                        $result['validationErrors'] = $errors;
                        $result['paymentMethods'] = $this->getPaymentMethods($Draft);

                        return $result;
                    });
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'order_draft_review',
            description: 'Refreshes a draft using current catalog prices and basket rules. Returns current totals, '
                . 'validation errors and available payment methods. Run before submitting a draft or reorder. '
                . 'Fails if products or quantities would be removed or changed.',
            inputSchema: $this->objectSchema(['orderId' => $identifier], ['orderId'])
        );

        $serverBuilder->addTool(
            function (string $orderId, float $expectedTotal, string $currency): array | CallToolResult {
                try {
                    $this->checkPermission('quiqqer.order.create');

                    if (!is_finite($expectedTotal) || $expectedTotal < 0 || trim($orderId) === '') {
                        throw new QUI\Exception('Invalid order submission parameters.');
                    }

                    return QUI::getDataBaseConnection()->transactional(
                        function () use ($orderId, $expectedTotal, $currency): array {
                            $this->lockDraft($orderId);
                            $Submitted = $this->getSubmittedOrder($orderId);

                            if ($Submitted !== null) {
                                $this->checkCreator($Submitted);
                                $result = $this->parseOrder($Submitted, true);
                                $result['alreadySubmitted'] = true;

                                return $result;
                            }

                            $Draft = $this->checkDraft($this->getOrder($orderId, true));

                            if ($Draft->getUUID() !== $orderId) {
                                throw new QUI\Exception('Submit the stable draft UUID/hash, not its numeric id or number.');
                            }

                            $quantities = $this->getArticleQuantities($Draft);
                            $errors = $this->getSubmissionErrors($Draft);

                            if ($errors !== []) {
                                throw new QUI\Exception(implode(' ', $errors));
                            }

                            $Order = $Draft->createOrder(
                                Server::getRequestUser(),
                                function (OrderInProcess $Recalculated) use ($quantities, $expectedTotal, $currency): void {
                                    $this->checkArticleQuantities($Recalculated, $quantities);
                                    $errors = $this->getSubmissionErrors($Recalculated);

                                    if ($errors !== []) {
                                        throw new QUI\Exception(implode(' ', $errors));
                                    }

                                    $Currency = $Recalculated->getCurrency();
                                    $totals = $Recalculated->getArticles()->getCalculations();

                                    if (
                                        $Currency->getCode() !== $currency
                                        || !isset($totals['sum'])
                                        || round((float)$totals['sum'], $Currency->getPrecision())
                                            !== round($expectedTotal, $Currency->getPrecision())
                                    ) {
                                        throw new QUI\Exception('Order total or currency changed. Review the draft again.');
                                    }
                                }
                            );
                            $result = $this->parseOrder($Order, true);
                            $result['alreadySubmitted'] = false;

                            return $result;
                        }
                    );
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'order_draft_submit',
            description: 'Places a real ERP order from a draft owned by the request user (or accessible to a superuser). '
                . 'Use the draft UUID from order_draft_review and its totals.sum and currency. Recalculates and validates '
                . 'immediately before creation. Automatic invoicing follows the installed order settings. '
                . 'Retrying the same UUID returns the existing order.',
            inputSchema: $this->objectSchema([
                'orderId' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'description' => 'Stable draft UUID/hash returned by order_draft_review. Use this for retries too.'
                ],
                'expectedTotal' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'description' => 'Reviewed gross total, from totals.sum.'
                ],
                'currency' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'description' => 'Reviewed currency code, for example EUR.'
                ]
            ], ['orderId', 'expectedTotal', 'currency'])
        );

        $serverBuilder->addTool(
            function (string $orderId): array | CallToolResult {
                try {
                    $this->checkPermission('quiqqer.order.copy');
                    $this->checkPermission('quiqqer.order.create');
                    $Source = $this->getOrder($orderId, false);

                    return QUI::getDataBaseConnection()->transactional(function () use ($Source): array {
                        $Draft = $this->createDraft();
                        $Draft->setCustomer($Source->getCustomer());
                        $Draft->setCurrency($Source->getCurrency());
                        $Draft->setInvoiceAddress($Source->getInvoiceAddress());

                        if ($Source->hasDeliveryAddress()) {
                            $Draft->setDeliveryAddress($Source->getDeliveryAddress());
                        }

                        // Recalculate a clean article snapshot without inherited payment or invoice state.
                        $fields = $this->getDataSchema()['properties']['articles']['items']['properties'];

                        $sourceArticles = $Source->getArticles()->toArray();

                        foreach ($sourceArticles['articles'] ?? [] as $article) {
                            $Draft->addArticle(new Article(array_intersect_key($article, $fields)));
                        }

                        $Draft->getArticles()->recalculate();
                        $Draft->save(Server::getRequestUser());
                        $result = $this->parseOrder($Draft, true);
                        $result['sourceOrderId'] = $Source->getUUID();
                        $result['requiresReview'] = true;

                        return $result;
                    });
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'order_reorder',
            description: 'Prepares a fresh draft from an existing order, copying customer, addresses, currency '
                . 'and article snapshots. Historical prices and availability must be reviewed before creating the order. '
                . 'Payment, invoice, completion and shipping state are not copied.',
            inputSchema: $this->objectSchema(['orderId' => $identifier], ['orderId'])
        );

        $serverBuilder->addTool(
            function (string $orderId, array $data): array | CallToolResult {
                try {
                    $this->checkPermission('quiqqer.order.update');
                    $this->validateObject($data, $this->getDataSchema());
                    $Order = $this->getOrder($orderId, false);

                    if ($Order->isSuccessful() || $Order->isPosted()) {
                        $restricted = array_diff(array_keys($data), ['comment', 'status']);

                        if ($restricted !== []) {
                            throw new QUI\Exception('Completed or invoiced orders only allow comment and status changes.');
                        }
                    }

                    return $this->saveData($Order, $data);
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'order_update',
            description: 'Updates an existing order with the order update permission. Completed or invoiced orders '
                . 'only allow comments and processing status changes. No payment or invoice state can be changed.',
            inputSchema: $this->objectSchema([
                'orderId' => $identifier,
                'data' => $dataSchema
            ], ['orderId', 'data'])
        );
    }

    private function checkPermission(?string $domainPermission = null): void
    {
        $User = Server::getRequestUser();
        Permission::checkPermission(self::PERMISSION, $User);

        if ($domainPermission !== null) {
            Permission::checkPermission($domainPermission, $User);
        }
    }

    protected function createDraft(): OrderInProcess
    {
        return Factory::getInstance()->createOrderInProcess(Server::getRequestUser());
    }

    protected function getOrder(string $orderId, bool $draft): Order | OrderInProcess
    {
        $orderId = trim($orderId);

        if ($orderId === '') {
            throw new QUI\Exception('Order identifier must not be empty.');
        }

        $Handler = Handler::getInstance();
        $table = $draft ? $Handler->tableOrderProcess() : $Handler->table();
        $Query = QUI::getDataBaseConnection()->createQueryBuilder();
        $conditions = [
            Doctrine::quoteIdentifier('hash') . ' = :identifier',
            Doctrine::quoteIdentifier('id_str') . ' = :identifier'
        ];

        if (ctype_digit($orderId)) {
            $conditions[] = Doctrine::quoteIdentifier('id') . ' = :numericId';
            $Query->setParameter('numericId', (int)$orderId);
        }

        $id = $Query
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier($table))
            ->where($Query->expr()->or(...$conditions))
            ->setParameter('identifier', $orderId)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if ($id === false) {
            throw new QUI\Exception('Order or draft not found.', 404);
        }

        return $draft ? $Handler->getOrderInProcess($id) : $Handler->get($id);
    }

    private function checkDraft(Order | OrderInProcess $Draft): OrderInProcess
    {
        if (
            !$Draft instanceof OrderInProcess
            || $Draft->getOrderId()
            || $Draft->isSuccessful()
            || $Draft->isPosted()
        ) {
            throw new QUI\Exception('Only unsubmitted order drafts can be edited.');
        }

        $this->checkCreator($Draft);

        return $Draft;
    }

    private function checkCreator(Order | OrderInProcess $Order): void
    {
        $User = Server::getRequestUser();

        if (
            !$User->isSU()
            && !QUI::getUsers()->isSystemUser($User)
            && $Order->getCreateUser()->getUUID() !== $User->getUUID()
        ) {
            throw new QUI\Permissions\Exception('The request user does not own this order or draft.', 403);
        }
    }

    /**
     * Serialize submissions of the same draft before checking for an existing order.
     * SQLite serializes writes at database level and has no FOR UPDATE support.
     */
    private function lockDraft(string $hash): void
    {
        $Connection = QUI::getDataBaseConnection();
        $Query = $Connection->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier(Handler::getInstance()->tableOrderProcess()))
            ->where(Doctrine::quoteIdentifier('hash') . ' = :hash')
            ->setParameter('hash', $hash);

        if (!$Connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $Query->forUpdate();
        }

        $Query->executeQuery()->fetchOne();
    }

    private function getSubmittedOrder(string $hash): ?Order
    {
        $Handler = Handler::getInstance();
        $id = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier($Handler->table()))
            ->where(Doctrine::quoteIdentifier('hash') . ' = :hash')
            ->andWhere(Doctrine::quoteIdentifier('order_process_id') . ' = :hash')
            ->setParameter('hash', $hash)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return $id === false ? null : $Handler->get($id);
    }

    /** @return array<string, float> */
    private function getArticleQuantities(OrderInProcess $Draft): array
    {
        $data = $Draft->getArticles()->toArray();
        $quantities = [];

        foreach ($data['articles'] ?? [] as $article) {
            $id = (int)($article['id'] ?? 0);
            $key = $id > 0 ? 'product:' . $id : 'text:' . ($article['title'] ?? '');
            $quantity = $article['quantity'] ?? 0;

            if (!is_numeric($quantity) || !is_finite((float)$quantity) || (float)$quantity <= 0) {
                throw new QUI\Exception('Order articles require a positive quantity.');
            }

            $quantities[$key] = ($quantities[$key] ?? 0.0) + (float)$quantity;
        }

        if ($quantities === []) {
            throw new QUI\Exception('Cannot submit an order without articles.');
        }

        ksort($quantities);

        return $quantities;
    }

    /** @param array<string, float> $expected */
    private function checkArticleQuantities(OrderInProcess $Draft, array $expected): void
    {
        if ($this->getArticleQuantities($Draft) !== $expected) {
            throw new QUI\Exception('Order products or quantities changed. Update and review the draft again.');
        }
    }

    /** @return list<string> */
    private function getSubmissionErrors(OrderInProcess $Draft): array
    {
        $errors = [];

        foreach ([$Draft->getInvoiceAddress(), $Draft->hasDeliveryAddress() ? $Draft->getDeliveryAddress() : null] as $Address) {
            if ($Address === null) {
                continue;
            }

            try {
                CustomerData::validateAddress($Address);
            } catch (QUI\Exception $Exception) {
                $errors[] = $Exception->getMessage();
            }
        }

        $Payment = $Draft->getPayment();

        if ($Payment !== null && !$this->canUsePayment($Draft, $Payment)) {
            $errors[] = 'The selected payment method is not available for this order.';
        }

        return $errors;
    }

    private function canUsePayment(
        Order | OrderInProcess $Order,
        QUI\ERP\Accounting\Payments\Types\Payment $Payment
    ): bool {
        return $Payment->canUsedBy($Order->getCustomer())
            && $Payment->isCurrencySupported($Order->getCurrency())
            && $Payment->canUsedInOrder($Order);
    }

    /** @return list<array<string, mixed>> */
    private function getPaymentMethods(OrderInProcess $Draft): array
    {
        $result = [];

        foreach (Payments::getInstance()->getUserPayments($Draft->getCustomer()) as $Payment) {
            if (!$this->canUsePayment($Draft, $Payment)) {
                continue;
            }

            $result[] = [
                'id' => $Payment->getId(),
                'title' => $Payment->getTitle(),
                'description' => $Payment->getDescription()
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function saveData(Order | OrderInProcess $Order, array $data): array
    {
        return QUI::getDataBaseConnection()->transactional(function () use ($Order, $data): array {
            $this->applyData($Order, $data);
            $Order->save(Server::getRequestUser());

            return $this->parseOrder($Order, true);
        });
    }

    /** @param array<string, mixed> $data */
    private function applyData(Order | OrderInProcess $Order, array $data): void
    {
        if (isset($data['customerId'])) {
            $Order->setCustomer(QUI::getUsers()->get($data['customerId']));
        }

        if (isset($data['currency'])) {
            $Order->setCurrency(QUI\ERP\Currency\Handler::getCurrency($data['currency']));
        }

        if (isset($data['addressInvoice'])) {
            $Order->setInvoiceAddress($data['addressInvoice']);
        }

        if (isset($data['addressDelivery'])) {
            if ($data['addressDelivery'] === []) {
                $Order->removeDeliveryAddress();
            } else {
                $Order->setDeliveryAddress($data['addressDelivery']);
            }
        }

        if (isset($data['articles'])) {
            $Order->clearArticles();

            foreach ($data['articles'] as $article) {
                $Order->addArticle(new Article($article));
            }

            $Order->getArticles()->recalculate();
        }

        if (isset($data['paymentId'])) {
            $Payment = Payments::getInstance()->getPayment($data['paymentId']);

            if (!$this->canUsePayment($Order, $Payment)) {
                throw new QUI\Exception('The selected payment method is not available for this order.');
            }

            $Order->setPayment($data['paymentId']);
        }

        if (isset($data['status'])) {
            $Order->setProcessingStatus($data['status']);
        }

        if (isset($data['comment'])) {
            $Order->addComment($data['comment']);
        }
    }

    /** @return array<string, mixed> */
    private function searchOrders(
        string $query,
        int $limit,
        int $offset,
        bool $draft,
        string $customerId,
        ?int $status,
        string $order
    ): array {
        if (!in_array($order, ['id ASC', 'id DESC', 'c_date ASC', 'c_date DESC'], true)) {
            throw new QUI\Exception('Invalid order sort.');
        }

        $limit = min(100, max(1, $limit));
        $offset = max(0, $offset);
        $Handler = Handler::getInstance();
        $table = $draft ? $Handler->tableOrderProcess() : $Handler->table();
        $Query = QUI::getDataBaseConnection()->createQueryBuilder();
        $Query->from(Doctrine::quoteIdentifier($table));

        if ($query !== '') {
            $conditions = [];

            foreach (['id_str', 'hash'] as $field) {
                $conditions[] = Doctrine::quoteIdentifier($field) . ' LIKE :query';
            }

            if (ctype_digit($query)) {
                $conditions[] = Doctrine::quoteIdentifier('id') . ' = :id';
                $Query->setParameter('id', (int)$query);
            }

            $Query->andWhere($Query->expr()->or(...$conditions));
            $Query->setParameter('query', '%' . $query . '%');
        }

        if ($customerId !== '') {
            $Query->andWhere(Doctrine::quoteIdentifier('customerId') . ' = :customer');
            $Query->setParameter('customer', $customerId);
        }

        if ($status !== null) {
            $Query->andWhere(Doctrine::quoteIdentifier('status') . ' = :status');
            $Query->setParameter('status', $status);
        }

        if ($draft) {
            $Query->andWhere(Doctrine::quoteIdentifier('order_id') . ' IS NULL');
        }

        $Count = clone $Query;
        $count = (int)$Count->select('COUNT(*)')->executeQuery()->fetchOne();
        [$field, $direction] = explode(' ', $order);
        $rows = $Query
            ->select(...array_map(Doctrine::quoteIdentifier(...), [
                'id',
                'id_str',
                'hash',
                'customerId',
                'status',
                'paid_status',
                'c_date',
                'currency'
            ]))
            ->orderBy(Doctrine::quoteIdentifier($field), $direction)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return [
            'draft' => $draft,
            'count' => $count,
            'limit' => $limit,
            'offset' => $offset,
            'orders' => $rows
        ];
    }

    /** @return array<string, mixed> */
    private function parseOrder(Order | OrderInProcess $Order, bool $includeArticles): array
    {
        $Articles = $Order->getArticles()->calc();
        $result = [
            'id' => $Order->getId(),
            'uuid' => $Order->getUUID(),
            'prefixedNumber' => $Order->getPrefixedNumber(),
            'draft' => $Order instanceof OrderInProcess,
            'successful' => (bool)$Order->isSuccessful(),
            'invoiced' => $Order->isPosted(),
            'customerId' => $Order->getCustomer()->getUUID(),
            'invoiceAddress' => $Order->getInvoiceAddress()->getAttributes(),
            'deliveryAddress' => $Order->hasDeliveryAddress() ? $Order->getDeliveryAddress()->getAttributes() : null,
            'currency' => $Order->getCurrency()->getCode(),
            'date' => $Order->getCreateDate(),
            'status' => $Order->getProcessingStatus()?->getId(),
            'paidStatus' => $Order->getAttribute('paid_status'),
            'paymentId' => $Order->getPayment()?->getId(),
            'totals' => $Articles->getCalculations(),
            'comments' => $Order->getComments()->toArray()
        ];

        if ($includeArticles) {
            $fields = $this->getDataSchema()['properties']['articles']['items']['properties'];
            $fields['sum'] = [];
            $articles = $Articles->toArray();
            $result['articles'] = array_map(
                static fn(array $article): array => array_intersect_key($article, $fields),
                $articles['articles'] ?? []
            );
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function getDataSchema(): array
    {
        $address = $this->objectSchema([
            'id' => ['type' => 'integer', 'minimum' => 0],
            'salutation' => ['type' => 'string'],
            'firstname' => ['type' => 'string'],
            'lastname' => ['type' => 'string'],
            'company' => ['type' => 'string'],
            'street_no' => ['type' => 'string'],
            'zip' => ['type' => 'string'],
            'city' => ['type' => 'string'],
            'country' => ['type' => 'string']
        ]);

        return $this->objectSchema([
            'customerId' => ['type' => 'string', 'minLength' => 1],
            'currency' => ['type' => 'string', 'minLength' => 1],
            'addressInvoice' => $address,
            'addressDelivery' => $address,
            'comment' => ['type' => 'string', 'minLength' => 1],
            'status' => ['type' => 'integer', 'minimum' => 0],
            'paymentId' => ['type' => 'integer', 'minimum' => 0],
            'articles' => [
                'type' => 'array',
                'maxItems' => 100,
                'items' => $this->objectSchema([
                    'id' => ['type' => 'integer', 'minimum' => 0],
                    'articleNo' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'unitPrice' => ['type' => 'number', 'minimum' => 0],
                    'quantity' => ['type' => 'number', 'exclusiveMinimum' => 0],
                    'quantityUnit' => ['type' => 'string'],
                    'vat' => ['type' => 'number', 'minimum' => 0]
                ], ['title', 'unitPrice', 'quantity'])
            ]
        ]);
    }

    /**
     * @param array<string, mixed> $properties
     * @param list<string> $required
     * @return array<string, mixed>
     */
    private function objectSchema(array $properties, array $required = []): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
            'required' => $required
        ];
    }

    /**
     * Validate payloads at the callback boundary as well as through the MCP schema.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $schema
     */
    private function validateObject(array $data, array $schema): void
    {
        if (array_diff(array_keys($data), array_keys($schema['properties'])) !== []) {
            throw new QUI\Exception('Unsupported order data field.');
        }

        if (array_diff($schema['required'], array_keys($data)) !== []) {
            throw new QUI\Exception('Required order data field is missing.');
        }

        foreach ($data as $key => $value) {
            $field = $schema['properties'][$key];
            $valid = match ($field['type']) {
                'string' => is_string($value) && strlen($value) >= ($field['minLength'] ?? 0),
                'integer' => is_int($value) && $value >= ($field['minimum'] ?? PHP_INT_MIN),
                'number' => (is_int($value) || is_float($value))
                    && is_finite((float)$value)
                    && $value >= ($field['minimum'] ?? -PHP_FLOAT_MAX)
                    && (!isset($field['exclusiveMinimum']) || $value > $field['exclusiveMinimum']),
                'object' => is_array($value),
                'array' => is_array($value) && array_is_list($value) && count($value) <= $field['maxItems'],
                default => false
            };

            if (!$valid) {
                throw new QUI\Exception('Invalid order data field: ' . $key);
            }

            if ($field['type'] === 'object') {
                $this->validateObject($value, $field);
            } elseif ($field['type'] === 'array') {
                foreach ($value as $entry) {
                    if (!is_array($entry)) {
                        throw new QUI\Exception('Invalid order article.');
                    }

                    $this->validateObject($entry, $field['items']);
                }
            }
        }
    }
}
