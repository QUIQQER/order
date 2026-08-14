<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\ArticleInterface;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Comments;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Address;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\ProcessingStatus\Status;
use QUI\ERP\Order\ProcessingStatus\StatusUnknown;
use QUI\ERP\Accounting\Payments\Api\AbstractPayment;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Product\Types\DigitalProduct;
use QUI\ERP\Products\Product\Types\Product as ProductType;
use QUI\ERP\User;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\Users\Address as UserAddress;
use QUITests\ERP\Order\Fixtures\FrontendMessageTestOrder;
use QUITests\ERP\Order\Fixtures\InvoiceExceptionOrder;
use ReflectionClass;
use ReflectionProperty;

class OrderUnitTest extends TestCase
{
    protected function setUp(): void
    {
        self::setProductCache([]);
    }

    protected function tearDown(): void
    {
        self::setProductCache([]);
    }

    public function testSimpleAccessorsFromAbstractOrder(): void
    {
        $Order = $this->createOrderWithoutConstructor();

        $this->setProperty($Order, 'id', 12);
        $this->setProperty($Order, 'idStr', 'ORD-12');
        $this->setProperty($Order, 'idPrefix', 'ORD-');
        $this->setProperty($Order, 'globalProcessId', 'G-1');
        $this->setProperty($Order, 'hash', 'hash-abc');
        $this->setProperty($Order, 'successful', 1);

        $this->assertSame(12, $Order->getId());
        $this->assertSame(12, $Order->getCleanId());
        $this->assertSame('ORD-12', $Order->getPrefixedNumber());
        $this->assertSame('ORD-12', $Order->getPrefixedId());
        $this->assertSame('ORD-', $Order->getIdPrefix());
        $this->assertSame('G-1', $Order->getGlobalProcessId());
        $this->assertSame('hash-abc', $Order->getUUID());
        $this->assertSame('hash-abc', $Order->getHash());
        $this->assertSame(1, $Order->isSuccessful());
    }

    public function testOrderDataLifecycle(): void
    {
        $Order = $this->createOrderWithoutConstructor();
        $this->setProperty($Order, 'data', []);

        $this->assertNull($Order->getDataEntry('missing'));

        $Order->setData('foo', 'bar');
        $Order->setData('answer', 42);

        $this->assertSame('bar', $Order->getDataEntry('foo'));
        $this->assertSame(42, $Order->getDataEntry('answer'));
        $this->assertSame(
            [
                'foo' => 'bar',
                'answer' => 42
            ],
            $Order->getData()
        );

        $Order->removeData('foo');
        $Order->removeData('does-not-exist');

        $this->assertNull($Order->getDataEntry('foo'));
        $this->assertSame(['answer' => 42], $Order->getData());
    }

    public function testDeliveryAddressLifecycleAndParsing(): void
    {
        $Order = $this->createOrderWithoutConstructor();
        $this->setProperty($Order, 'addressDelivery', []);

        $this->assertFalse($Order->hasDeliveryAddress());

        $Order->setDeliveryAddress([
            'id' => 0,
            'firstname' => 'Test',
            'unknown' => 'will-be-removed'
        ]);

        $this->assertFalse($Order->hasDeliveryAddress());
        $this->assertSame(
            [
                'id' => 0,
                'firstname' => 'Test'
            ],
            $this->getProperty($Order, 'addressDelivery')
        );

        $Order->setDeliveryAddress([
            'id' => 5,
            'city' => 'Aachen',
            'zip' => '52062',
            'unknown' => 'ignored'
        ]);

        $this->assertTrue($Order->hasDeliveryAddress());
        $this->assertSame(
            [
                'id' => 5,
                'city' => 'Aachen',
                'zip' => '52062'
            ],
            $this->getProperty($Order, 'addressDelivery')
        );

        $Order->clearAddressDelivery();
        $this->assertFalse($Order->hasDeliveryAddress());

        $Order->setDeliveryAddress(['id' => 9, 'city' => 'X']);
        $Order->removeDeliveryAddress();
        $this->assertFalse($Order->hasDeliveryAddress());
    }

    public function testInvoiceAddressParsingAndClearing(): void
    {
        $Order = $this->createOrderWithoutConstructor();
        $this->setProperty($Order, 'addressInvoice', []);

        $Order->setInvoiceAddress([
            'id' => 77,
            'firstname' => 'Max',
            'lastname' => 'Mustermann',
            'company' => 'ACME',
            'notAllowed' => 'x'
        ]);

        $this->assertSame(
            [
                'id' => 77,
                'firstname' => 'Max',
                'lastname' => 'Mustermann',
                'company' => 'ACME'
            ],
            $this->getProperty($Order, 'addressInvoice')
        );

        $Order->clearAddressInvoice();
        $this->assertSame([], $this->getProperty($Order, 'addressInvoice'));
    }

    public function testCustomDataAccessorsAndEmptyCustomerFiles(): void
    {
        $Order = $this->createOrderWithoutConstructor();

        $this->setProperty($Order, 'customData', [
            'a' => 1,
            'b' => 'two'
        ]);

        $this->assertSame(1, $Order->getCustomDataEntry('a'));
        $this->assertNull($Order->getCustomDataEntry('missing'));
        $this->assertSame(['a' => 1, 'b' => 'two'], $Order->getCustomData());

        $this->assertSame([], $Order->getCustomerFiles());
        $this->assertSame([], $Order->getCustomerFiles(true));
    }

    public function testPaymentDataLifecycleAndClearPayment(): void
    {
        $Order = $this->createOrderWithoutConstructor();

        $Order->setPaymentData('token', 'abc');
        $Order->setPaymentData('tries', 2);
        $Order->setPaymentData('confirmed', false);
        $Order->setPaymentData('metadata', null);

        $this->assertSame(
            [
                'token' => 'abc',
                'tries' => 2,
                'confirmed' => false,
                'metadata' => null
            ],
            $Order->getPaymentData()
        );
        $this->assertSame('abc', $Order->getPaymentDataEntry('token'));
        $this->assertNull($Order->getPaymentDataEntry('missing'));

        $this->setProperty($Order, 'paymentId', 99);
        $this->setProperty($Order, 'paymentMethod', 'dummy');
        $Order->clearPayment();

        $this->assertNull($this->getProperty($Order, 'paymentId'));
        $this->assertNull($this->getProperty($Order, 'paymentMethod'));
    }

    public function testInvalidPaymentDataDoesNotChangeExistingData(): void
    {
        $Order = $this->createOrderWithoutConstructor();
        $Order->setPaymentData('token', 'abc');
        $paymentData = $Order->getPaymentData();
        $resource = fopen('php://memory', 'r');

        self::assertIsResource($resource);

        try {
            $Order->setPaymentData('invalid', ['resource' => $resource]);
            self::fail('An array containing a resource must not be accepted as payment data.');
        } catch (\JsonException) {
        } finally {
            fclose($resource);
        }

        self::assertSame($paymentData, $Order->getPaymentData());

        try {
            $Order->setPaymentData('amount', INF);
            self::fail('An infinite number must not be accepted as payment data.');
        } catch (\JsonException) {
        }

        self::assertSame($paymentData, $Order->getPaymentData());
    }

    public function testIsPaidUsesPaidStatusAttribute(): void
    {
        $Order = $this->createOrderWithoutConstructor();

        $Order->setAttribute('paid_status', \QUI\ERP\Constants::PAYMENT_STATUS_PAID);
        $this->assertTrue($Order->isPaid());

        $Order->setAttribute('paid_status', \QUI\ERP\Constants::PAYMENT_STATUS_OPEN);
        $this->assertFalse($Order->isPaid());
    }

    public function testCommentsHistoryAndStatusMails(): void
    {
        $Order = $this->createOrderWithoutConstructor();
        $Comments = new Comments();
        $History = new Comments();
        $StatusMails = new Comments();

        $this->setProperty($Order, 'Comments', $Comments);
        $this->setProperty($Order, 'History', $History);
        $this->setProperty($Order, 'StatusMails', $StatusMails);

        $Order->addComment('<script>alert(1)</script><b>ok</b>');
        $Order->addHistory('history-entry');
        $Order->addStatusMail('line1<br>line2 <b>x</b>');

        $this->assertSame($Comments, $Order->getComments());
        $this->assertSame($History, $Order->getHistory());
        $this->assertSame($StatusMails, $Order->getStatusMails());
        $this->assertStringContainsString('<b>ok</b>', $Comments->toArray()[0]['message']);
        $this->assertSame('history-entry', $History->toArray()[0]['message']);
        $this->assertSame("line1\nline2 x", $StatusMails->toArray()[0]['message']);
    }

    public function testGetProcessingStatusReturnsPresetStatus(): void
    {
        $Order = $this->createOrderWithoutConstructor();
        $Status = new StatusUnknown();

        $this->setProperty($Order, 'Status', $Status);
        $this->assertSame($Status, $Order->getProcessingStatus());
    }

    public function testGetInvoiceTypeReturnsEmptyStringOnException(): void
    {
        $this->assertSame('', (new InvoiceExceptionOrder())->getInvoiceType());
    }

    public function testCurrencyAddressObjectsAndArticleOperationsAreDelegated(): void
    {
        $Order = $this->createOrderWithoutConstructor();
        $Articles = $this->createMock(ArticleList::class);
        $Currency = $this->createMock(Currency::class);
        $Article = $this->createMock(ArticleInterface::class);
        $DeliveryAddress = $this->createMock(Address::class);
        $DeliveryAddress->method('getAttributes')->willReturn(['id' => 5, 'city' => 'Berlin']);
        $InvoiceAddress = $this->createMock(UserAddress::class);
        $InvoiceAddress->method('getAttributes')->willReturn(['city' => 'London']);
        $InvoiceAddress->method('getUUID')->willReturn('invoice-address');

        $this->setProperty($Order, 'Articles', $Articles);
        $Articles->expects(self::once())->method('setCurrency')->with($Currency);
        $Articles->expects(self::once())->method('addArticle')->with($Article);
        $Articles->expects(self::once())->method('removeArticle')->with(2);
        $Articles->expects(self::once())->method('replaceArticle')->with($Article, 3);
        $Articles->expects(self::once())->method('clear');
        $Articles->method('count')->willReturn(4);

        $Order->setCurrency($Currency);
        $Order->setDeliveryAddress($DeliveryAddress);
        $Order->setInvoiceAddress($InvoiceAddress);
        $Order->addArticle($Article);
        $Order->removeArticle(2);
        $Order->replaceArticle($Article, 3);
        $Order->clearArticles();

        self::assertSame($Currency, $Order->getCurrency());
        self::assertSame(['id' => 5, 'city' => 'Berlin'], $this->getProperty($Order, 'addressDelivery'));
        self::assertSame(
            ['city' => 'London', 'id' => 'invoice-address'],
            $this->getProperty($Order, 'addressInvoice')
        );
        self::assertSame(4, $Order->count());
    }

    public function testFrontendMessagesUsePersistenceHook(): void
    {
        $Order = new FrontendMessageTestOrder();
        $this->setProperty($Order, 'FrontendMessage', new Comments());

        $Order->addFrontendMessage('Visible message');

        self::assertSame('Visible message', $Order->getFrontendMessages()->toArray()[0]['message']);
        self::assertSame(1, $Order->frontendMessageSaveCalls);

        $Order->clearFrontendMessages();

        self::assertTrue($Order->getFrontendMessages()->isEmpty());
        self::assertSame(2, $Order->frontendMessageSaveCalls);
    }

    public function testArticleTypeDetectsPhysicalDigitalAndMixedOrders(): void
    {
        $Physical = $this->createMock(ProductType::class);
        $Digital = $this->createMock(DigitalProduct::class);
        self::setProductCache([700001 => $Physical, 700002 => $Digital]);

        $TextArticle = $this->createMock(ArticleInterface::class);
        $TextArticle->method('getId')->willReturn(0);
        $PhysicalArticle = $this->createMock(ArticleInterface::class);
        $PhysicalArticle->method('getId')->willReturn(700001);
        $DigitalArticle = $this->createMock(ArticleInterface::class);
        $DigitalArticle->method('getId')->willReturn(700002);

        $Order = $this->createOrderWithoutConstructor();
        $Articles = $this->createMock(ArticleList::class);
        $this->setProperty($Order, 'Articles', $Articles);
        $Articles->method('getArticles')->willReturnOnConsecutiveCalls(
            [$TextArticle, $PhysicalArticle],
            [$DigitalArticle],
            [$PhysicalArticle, $DigitalArticle]
        );

        self::assertSame(Order::ARTICLE_TYPE_PHYSICAL, $Order->getArticleType());
        self::assertSame(Order::ARTICLE_TYPE_DIGITAL, $Order->getArticleType());
        self::assertSame(Order::ARTICLE_TYPE_MIXED, $Order->getArticleType());
    }

    public function testProcessingStatusTracksRealChangesOnly(): void
    {
        $Order = $this->createOrderWithoutConstructor();
        $OldStatus = $this->createMock(Status::class);
        $OldStatus->method('getId')->willReturn(1);
        $NewStatus = $this->createMock(Status::class);
        $NewStatus->method('getId')->willReturn(2);
        $this->setProperty($Order, 'Status', $OldStatus);
        $this->setProperty($Order, 'status', 1);

        $Order->setProcessingStatus($OldStatus);
        self::assertFalse($this->getProperty($Order, 'statusChanged'));

        $Order->setProcessingStatus($NewStatus);
        self::assertTrue($this->getProperty($Order, 'statusChanged'));
        self::assertSame(2, $this->getProperty($Order, 'status'));
        self::assertSame($NewStatus, $Order->getProcessingStatus());
    }

    public function testCreationDateIgnoresInvalidInputAndNormalizesValidInput(): void
    {
        $Order = $this->createOrderWithoutConstructor();
        $this->setProperty($Order, 'cDate', '2020-01-01 00:00:00');

        $Order->setCreationDate('invalid-date');
        self::assertSame('2020-01-01 00:00:00', $Order->getCreateDate());

        $Order->setCreationDate('2024-03-04 05:06:07');
        self::assertSame('2024-03-04 05:06:07', $Order->getCreateDate());
        self::assertSame('2024-03-04 05:06:07', $Order->getAttribute('c_date'));
    }

    public function testCustomerAssignmentSupportsErpCustomerAndRemoval(): void
    {
        $Articles = $this->createMock(ArticleList::class);
        $Customer = $this->createMock(User::class);
        $Customer->method('getUUID')->willReturn('erp-customer');
        $Order = $this->createOrderWithoutConstructor();
        $this->setProperty($Order, 'Articles', $Articles);
        $this->setProperty($Order, 'customerId', 0);
        $this->setProperty($Order, 'Customer', null);

        $Articles->expects(self::exactly(2))->method('setUser');
        $Order->setCustomer($Customer);
        self::assertSame($Customer, $Order->getCustomer());

        $Order->setCustomer([]);
        self::assertSame(0, $this->getProperty($Order, 'customerId'));
        self::assertNull($this->getProperty($Order, 'Customer'));
    }

    public function testCustomerAssignmentConvertsCoreUserAndInitializesInvoiceAddress(): void
    {
        $Articles = $this->createMock(ArticleList::class);
        $Order = $this->createOrderWithoutConstructor();
        $this->setProperty($Order, 'Articles', $Articles);
        $this->setProperty($Order, 'customerId', 0);
        $this->setProperty($Order, 'Customer', null);
        $this->setProperty($Order, 'addressInvoice', []);

        $Order->setCustomer(\QUI::getUsers()->getSystemUser());

        self::assertNotNull($this->getProperty($Order, 'Customer'));
        self::assertNotEmpty($this->getProperty($Order, 'customerId'));
        self::assertIsArray($this->getProperty($Order, 'addressInvoice'));
    }

    public function testApprovalChecksPaymentProviderAndHandlesMissingPayment(): void
    {
        $Order = $this->createOrderWithoutConstructor();
        $this->setProperty($Order, 'paymentId', null);
        self::assertFalse($Order->isApproved());

        $PaymentType = $this->createMock(AbstractPayment::class);
        $PaymentType->method('isApproved')->with('approval-order')->willReturn(true);
        $Payment = $this->createMock(\QUI\ERP\Accounting\Payments\Types\Payment::class);
        $Payment->method('getPaymentType')->willReturn($PaymentType);
        $ApprovedOrder = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->getMock();
        $ApprovedOrder->method('getPayment')->willReturn($Payment);
        $this->setProperty($ApprovedOrder, 'hash', 'approval-order');
        $this->setProperty($ApprovedOrder, 'successful', 1);

        self::assertTrue($ApprovedOrder->isApproved());
        $this->setProperty($ApprovedOrder, 'successful', 0);
        self::assertFalse($ApprovedOrder->isApproved());
    }

    public function testDeliveryAddressFallsBackToInvoiceForEmptyAndSentinelData(): void
    {
        $Customer = $this->createMock(User::class);
        $Order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCustomer'])
            ->getMock();
        $Order->method('getCustomer')->willReturn($Customer);
        $this->setProperty($Order, 'addressInvoice', ['id' => 5, 'city' => 'Berlin']);

        $this->setProperty($Order, 'addressDelivery', ['id' => -1]);
        self::assertSame('Berlin', $Order->getDeliveryAddress()->getAttribute('city'));

        $this->setProperty($Order, 'addressDelivery', ['id' => 8, 'city' => '', 'zip' => '']);
        self::assertSame('Berlin', $Order->getDeliveryAddress()->getAttribute('city'));

        $this->setProperty($Order, 'addressDelivery', ['id' => 8, 'city' => 'Hamburg']);
        self::assertSame('Hamburg', $Order->getDeliveryAddress()->getAttribute('city'));
    }

    public function testTransactionGuardsAndNormalAddition(): void
    {
        $Order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['calculatePayments', 'hasInvoice'])
            ->getMock();
        $Order->method('hasInvoice')->willReturn(false);
        $Order->expects(self::once())->method('calculatePayments');
        $Articles = $this->createMock(ArticleList::class);
        $Articles->expects(self::once())->method('calc');
        $Articles->method('getCalculations')->willReturn([
            'currencyData' => ['code' => 'EUR'],
            'nettoSum' => 8.4,
            'subSum' => 10.0,
            'sum' => 10.0,
            'vatArray' => []
        ]);
        $this->setProperty($Order, 'Articles', $Articles);
        $this->setProperty($Order, 'History', new Comments());
        $this->setProperty($Order, 'hash', 'transaction-order');
        $Order->setAttribute('paid_data', []);
        $Order->setAttribute('paid_status', \QUI\ERP\Constants::PAYMENT_STATUS_ERROR);
        $Transaction = $this->createMock(Transaction::class);
        $Transaction->method('getHash')->willReturn('transaction-order');
        $Transaction->method('getAmount')->willReturn(10.0);
        $Transaction->method('getDate')->willReturn('invalid-date');
        $Transaction->method('getTxId')->willReturn('tx-new');

        $Order->addTransaction($Transaction);

        self::assertSame(
            \QUI\ERP\Constants::PAYMENT_STATUS_OPEN,
            $Order->getAttribute('paid_status')
        );
        self::assertIsInt($Order->getAttribute('paid_date'));
        self::assertCount(1, $Order->getHistory()->toArray());

        $Foreign = $this->createMock(Transaction::class);
        $Foreign->method('getHash')->willReturn('other-order');
        $Order->addTransaction($Foreign);
    }

    public function testDuplicateAndCompletedTransactionsStopBeforeLinking(): void
    {
        $Order = $this->createOrderWithoutConstructor();
        $this->setProperty($Order, 'hash', 'transaction-order');
        $Order->setAttribute('paid_data', [['txid' => 'existing']]);
        $Transaction = $this->createMock(Transaction::class);
        $Transaction->method('getTxId')->willReturn('existing');
        $Transaction->expects(self::never())->method('addLinkedHash');
        $Order->linkTransaction($Transaction);

        $Order->setAttribute('paid_data', []);
        $Order->setAttribute('paid_status', \QUI\ERP\Constants::PAYMENT_STATUS_PAID);
        $CompletedTransaction = $this->createMock(Transaction::class);
        $CompletedTransaction->method('getTxId')->willReturn('new');
        $CompletedTransaction->method('isHashLinked')->willReturn(false);
        $CompletedTransaction->expects(self::never())->method('addLinkedHash');
        $Order->linkTransaction($CompletedTransaction);
        self::assertTrue(true);
    }

    public function testAbstractOrderConstructorRejectsIncompleteData(): void
    {
        $this->expectException(\QUI\ERP\Exception::class);
        $this->createAbstractOrderMock([]);
    }

    public function testAbstractOrderHydratesOptionalDatabasePayload(): void
    {
        $CoreUser = \QUI::getUsers()->getSystemUser();
        $ErpUser = User::convertUserToErpUser($CoreUser);
        $customer = $ErpUser->getAttributes();
        $customer['id'] = $ErpUser->getUUID();
        $customer['uuid'] = $ErpUser->getUUID();
        $customer['address'] = $ErpUser->getStandardAddress()->getAttributes();
        $data = [
            'id' => 91,
            'id_str' => '',
            'id_prefix' => 'TEST-',
            'status' => 0,
            'customerId' => $ErpUser->getUUID(),
            'customer' => json_encode($customer, JSON_THROW_ON_ERROR),
            'addressInvoice' => json_encode($customer['address'], JSON_THROW_ON_ERROR),
            'addressDelivery' => json_encode(['id' => -1], JSON_THROW_ON_ERROR),
            'data' => '{"hydrated":true}',
            'articles' => '[]',
            'comments' => (new Comments())->toJSON(),
            'history' => (new Comments())->toJSON(),
            'frontendMessages' => (new Comments())->toJSON(),
            'status_mails' => (new Comments())->toJSON(),
            'invoice_id' => null,
            'temporary_invoice_id' => null,
            'payment_id' => null,
            'payment_method' => null,
            'payment_data' => null,
            'payment_time' => null,
            'payment_address' => null,
            'paid_data' => null,
            'paid_status' => 0,
            'paid_date' => null,
            'shipping_id' => null,
            'successful' => 0,
            'global_process_id' => '',
            'project_name' => 'phpunit-project',
            'order_process_id' => 'process-parent',
            'hash' => 'hydrated-order',
            'c_date' => '2024-01-02 03:04:05',
            'c_user' => $CoreUser->getUUID()
        ];

        $Order = $this->createAbstractOrderMock($data);

        self::assertSame(91, $Order->getId());
        self::assertSame('TEST-91', $Order->getPrefixedNumber());
        self::assertSame('hydrated-order', $Order->getGlobalProcessId());
        self::assertTrue($Order->getDataEntry('hydrated'));
        self::assertSame('phpunit-project', $Order->getAttribute('project_name'));
        self::assertSame('process-parent', $Order->getAttribute('order_process_id'));
        self::assertSame($ErpUser->getUUID(), $Order->getCustomer()->getUUID());
    }

    public function testShippingAssignmentAndRemovalUseValidatedIdentifier(): void
    {
        $Articles = $this->createMock(ArticleList::class);
        $Articles->method('count')->willReturn(1);
        $Shipping = $this->createMock(\QUI\ERP\Shipping\Api\ShippingInterface::class);
        $Shipping->method('getId')->willReturn(37);
        $Order = $this->createOrderWithoutConstructor();
        $this->setProperty($Order, 'Articles', $Articles);
        $this->setProperty($Order, 'shippingId', null);

        $Order->validateShipping(null);
        $Order->setShipping($Shipping);
        self::assertSame(37, $this->getProperty($Order, 'shippingId'));
        $Order->removeShipping();
        self::assertNull($this->getProperty($Order, 'shippingId'));

        $this->setProperty($Order, 'shippingId', 999999);
        self::assertNull($Order->getShipping());
    }

    public function testProcessingStatusFallsBackToUnknownAndAcceptsNumericStatus(): void
    {
        $Order = $this->createOrderWithoutConstructor();
        $this->setProperty($Order, 'Status', null);
        $this->setProperty($Order, 'status', 0);

        $Status = $Order->getProcessingStatus();
        self::assertInstanceOf(StatusUnknown::class, $Status);

        $Order->setProcessingStatus(0);
        self::assertFalse($this->getProperty($Order, 'statusChanged'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createAbstractOrderMock(array $data): \QUI\ERP\Order\AbstractOrder
    {
        $abstractMethods = array_map(
            static fn(\ReflectionMethod $Method): string => $Method->getName(),
            array_filter(
                (new ReflectionClass(\QUI\ERP\Order\AbstractOrder::class))->getMethods(),
                static fn(\ReflectionMethod $Method): bool => $Method->isAbstract()
            )
        );

        return $this->getMockBuilder(\QUI\ERP\Order\AbstractOrder::class)
            ->setConstructorArgs([$data])
            ->onlyMethods($abstractMethods)
            ->getMock();
    }

    private function createOrderWithoutConstructor(): Order
    {
        return (new ReflectionClass(Order::class))->newInstanceWithoutConstructor();
    }

    private function setProperty(object $object, string $propertyName, mixed $value): void
    {
        $property = $this->findProperty($object, $propertyName);
        $property->setValue($object, $value);
    }

    private function getProperty(object $object, string $propertyName): mixed
    {
        $property = $this->findProperty($object, $propertyName);
        return $property->getValue($object);
    }

    private function findProperty(object $object, string $propertyName): ReflectionProperty
    {
        $reflection = new ReflectionClass($object);

        while ($reflection) {
            if ($reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);
                $property->setAccessible(true);

                return $property;
            }

            $reflection = $reflection->getParentClass();
        }

        $this->fail('Property not found: ' . $propertyName);
    }

    /**
     * @param array<int, ProductType> $products
     */
    private static function setProductCache(array $products): void
    {
        $Reflection = new ReflectionClass(Products::class);
        $Reflection->getProperty('list')->setValue(null, $products);
    }
}
