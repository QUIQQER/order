<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Address as ERPAddress;
use QUI\ERP\ErpEntityInterface;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Mail;
use QUI\ERP\Order\Order;
use QUI\ERP\User as ERPUser;
use QUI\Interfaces\Users\User;
use QUI\Users\Address;
use ReflectionMethod;

class MailUnitTest extends TestCase
{
    public function testDateFormatRejectsInvalidDateAndFormatsTimestamp(): void
    {
        $Locale = $this->createLocale();

        self::assertFalse(Mail::dateFormat('not-a-valid-date', $Locale));
        self::assertIsString(Mail::dateFormat(1704067200, $Locale));
    }

    public function testOrderLocaleVariablesUsePlaceholdersWithoutCustomerAddress(): void
    {
        $Locale = $this->createMock(\QUI\Locale::class);
        $Locale->method('getCurrent')->willReturn('en');
        $Locale->method('getLocalesByLang')->willReturn(['en_US']);

        $Customer = $this->createMock(User::class);
        $Customer->method('getName')->willReturn('');
        $Customer->method('getAttribute')->willReturn('');
        $Customer->method('getStandardAddress')->willReturn(null);
        $Customer->method('getLocale')->willReturn($Locale);

        $Order = $this->createMock(ErpEntityInterface::class);
        $Order->method('getUUID')->willReturn('phpunit-order');
        $Order->method('getPrefixedNumber')->willReturn('PHPUNIT-ORDER');
        $Order->method('getAttribute')->willReturn(null);

        $Method = new ReflectionMethod(Mail::class, 'getOrderLocaleVar');
        $variables = $Method->invoke(null, $Order, $Customer);

        self::assertSame('', $variables['user']);
        self::assertSame('', $variables['name']);
        self::assertSame('', $variables['company']);
        self::assertSame('', $variables['companyOrName']);
        self::assertSame('', $variables['address']);
        self::assertSame('', $variables['salutation']);
        self::assertSame('', $variables['firstname']);
        self::assertSame('', $variables['lastname']);
        self::assertSame('', $variables['email']);
    }

    public function testOrderLocaleVariablesUseAddressFallbacks(): void
    {
        $Locale = $this->createLocale();
        $Address = $this->createMock(Address::class);
        $Address->method('getName')->willReturn('Ada Lovelace');
        $Address->method('getMailList')->willReturn(['ada@example.test']);
        $Address->method('render')->willReturn('Rendered address');
        $Address->method('getAttribute')->willReturnCallback(
            static fn(string $name): string => match ($name) {
                'company' => 'Analytical Engines Ltd.',
                'salutation' => 'ms',
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
                default => ''
            }
        );

        $Customer = $this->createMock(User::class);
        $Customer->method('getName')->willReturn('');
        $Customer->method('getAttribute')->with('email')->willReturn('');
        $Customer->method('getStandardAddress')->willReturn($Address);
        $Customer->method('getLocale')->willReturn($Locale);

        $Order = $this->createMock(ErpEntityInterface::class);
        $Order->method('getUUID')->willReturn('order-uuid');
        $Order->method('getPrefixedNumber')->willReturn('ORD-42');
        $Order->method('getAttribute')->willReturnCallback(
            static fn(string $name): mixed => match ($name) {
                'hash' => 'order-hash',
                'date' => '2024-01-01',
                default => null
            }
        );

        $variables = $this->invokeMailMethod('getOrderLocaleVar', $Order, $Customer);

        self::assertSame('Ada Lovelace', $variables['user']);
        self::assertSame('Analytical Engines Ltd.', $variables['company']);
        self::assertSame('Analytical Engines Ltd.', $variables['companyOrName']);
        self::assertSame('Rendered address', $variables['address']);
        self::assertSame('ada@example.test', $variables['email']);
        self::assertSame('ms', $variables['salutation']);
        self::assertSame('Ada', $variables['firstname']);
        self::assertSame('Lovelace', $variables['lastname']);
    }

    public function testCompanyOrNameFallsBackToCustomerName(): void
    {
        $Address = $this->createMock(Address::class);
        $Address->method('getAttribute')->with('company')->willReturn('');
        $Customer = $this->createMock(User::class);
        $Customer->method('getStandardAddress')->willReturn($Address);
        $Customer->method('getName')->willReturn('Grace Hopper');

        self::assertSame('Grace Hopper', $this->invokeMailMethod('getCompanyOrName', $Customer));
    }

    public function testOrderConfirmationWithoutCustomerEmailStopsEarly(): void
    {
        $Customer = $this->createMock(ERPUser::class);
        $Customer->method('getAttribute')->with('email')->willReturn('');
        $Customer->method('getUUID')->willReturn('customer-uuid');

        $Order = $this->createMock(Order::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getUUID')->willReturn('order-uuid');

        Mail::sendOrderConfirmationMail($Order);
        self::assertTrue(true);
    }

    public function testShippingConfirmationWithoutAnyCustomerEmailThrows(): void
    {
        $Address = $this->createMock(ERPAddress::class);
        $Address->method('getMailList')->willReturn([]);
        $Customer = $this->createMock(ERPUser::class);
        $Customer->method('getLocale')->willReturn($this->createLocale());
        $Customer->method('getAddress')->willReturn($Address);
        $Customer->method('getAttribute')->with('email')->willReturn('');
        $Customer->method('getUUID')->willThrowException(new \QUI\Exception('Missing customer'));

        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getCustomer')->willReturn($Customer);

        $this->expectException(\QUI\Exception::class);
        Mail::sendOrderShippingConfirmation($Order);
    }

    private function createLocale(): \QUI\Locale
    {
        $Locale = $this->createMock(\QUI\Locale::class);
        $Locale->method('getCurrent')->willReturn('en');
        $Locale->method('getLocalesByLang')->willReturn(['en_US']);

        return $Locale;
    }

    private function invokeMailMethod(string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod(Mail::class, $method))->invoke(null, ...$arguments);
    }
}
