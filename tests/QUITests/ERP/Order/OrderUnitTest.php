<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Comments;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\ProcessingStatus\StatusUnknown;
use ReflectionClass;
use ReflectionProperty;

class OrderUnitTest extends TestCase
{
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

        $this->assertSame(
            [
                'token' => 'abc',
                'tries' => 2
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
        $Order = $this->createOrderWithoutConstructor();
        $this->setProperty($Order, 'invoiceId', 999999);

        $this->assertSame('', $Order->getInvoiceType());
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
}
