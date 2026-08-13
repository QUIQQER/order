<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\Locale;
use QUI\ERP\Address;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\PaymentReceiver;
use QUI\ERP\User;
use ReflectionClass;
use ReflectionProperty;

class PaymentReceiverUnitTest extends TestCase
{
    public function testTypeMetadataUsesGivenLocale(): void
    {
        $Locale = $this->createMock(Locale::class);
        $Locale->expects(self::once())
            ->method('get')
            ->with('quiqqer/order', 'PaymentReceiver.Order.title')
            ->willReturn('Localized order');

        self::assertSame('Order', PaymentReceiver::getType());
        self::assertSame('Localized order', PaymentReceiver::getTypeTitle($Locale));
    }

    public function testResolvedOrderDataIsExposedAsPaymentReceiverData(): void
    {
        $Address = $this->createMock(Address::class);
        $Currency = $this->createMock(Currency::class);
        $Customer = $this->createMock(User::class);
        $Customer->method('getStandardAddress')->willReturn($Address);
        $Customer->method('getAttribute')->with('customerNo')->willReturn('C-100');

        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getPrefixedNumber')->willReturn('ORD-42');
        $Order->method('getCurrency')->willReturn($Currency);
        $Order->method('getAttribute')->willReturnCallback(
            static fn(string $name): mixed => match ($name) {
                'date' => '2024-02-03 10:30:00',
                'payment_time' => '2024-02-17 23:59:59',
                'sum' => 119.99,
                'toPay' => 19.99,
                'paid' => 100.0,
                'paid_status' => 2,
                default => null
            }
        );

        $Receiver = $this->createReceiver($Order);

        self::assertSame($Address, $Receiver->getDebtorAddress());
        self::assertSame('ORD-42', $Receiver->getDocumentNo());
        self::assertSame('C-100', $Receiver->getDebtorNo());
        self::assertSame('2024-02-03 10:30:00', $Receiver->getDate()->format('Y-m-d H:i:s'));
        self::assertSame('2024-02-17 23:59:59', $Receiver->getDueDate()->format('Y-m-d H:i:s'));
        self::assertSame($Currency, $Receiver->getCurrency());
        self::assertSame(119.99, $Receiver->getAmountTotal());
        self::assertSame(19.99, $Receiver->getAmountOpen());
        self::assertSame(100.0, $Receiver->getAmountPaid());
        self::assertSame(2, $Receiver->getPaymentStatus());
    }

    public function testMissingDueDateIsReportedAsFalse(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getAttribute')->with('payment_time')->willReturn(null);

        self::assertFalse($this->createReceiver($Order)->getDueDate());
    }

    public function testInvalidDatesUseDocumentFallbacks(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getAttribute')->willReturnCallback(
            static fn(string $name): ?string => match ($name) {
                'date', 'payment_time' => 'not-a-valid-date',
                default => null
            }
        );

        $before = time();
        $Receiver = $this->createReceiver($Order);
        $documentTimestamp = $Receiver->getDate()->getTimestamp();
        $after = time();

        self::assertGreaterThanOrEqual($before, $documentTimestamp);
        self::assertLessThanOrEqual($after, $documentTimestamp);
        self::assertFalse($Receiver->getDueDate());
    }

    private function createReceiver(AbstractOrder $Order): PaymentReceiver
    {
        $Receiver = (new ReflectionClass(PaymentReceiver::class))->newInstanceWithoutConstructor();
        $Property = new ReflectionProperty(PaymentReceiver::class, 'Order');
        $Property->setValue($Receiver, $Order);

        return $Receiver;
    }
}
