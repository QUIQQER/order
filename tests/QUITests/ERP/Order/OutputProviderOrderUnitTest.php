<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Address;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Accounting\Payments\Api\AbstractPayment;
use QUI\ERP\Accounting\Payments\Methods\AdvancePayment\Payment as AdvancePayment;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\Output\OutputProviderOrder;
use QUI\ERP\User;
use QUI\Locale;
use ReflectionMethod;

class OutputProviderOrderUnitTest extends TestCase
{
    public function testTypeTitleAndDateFormatting(): void
    {
        $Locale = $this->createMock(Locale::class);
        $Locale->expects(self::once())
            ->method('get')
            ->with('quiqqer/order', 'OutputProvider.entity.title.Order')
            ->willReturn('Order document');

        self::assertSame('Order', OutputProviderOrder::getEntityType());
        self::assertSame('Order document', OutputProviderOrder::getEntityTypeTitle($Locale));
        self::assertIsString(OutputProviderOrder::dateFormat(null));
        self::assertIsString(OutputProviderOrder::dateFormat('2024-01-02'));
        self::assertFalse(OutputProviderOrder::dateFormat('not-a-date'));
    }

    public function testCustomerVariablesPreferContactPersonAndEmail(): void
    {
        $Address = $this->createMock(Address::class);
        $Address->method('getName')->willReturn('Address Name');
        $Address->method('getMailList')->willReturn(['address@example.test']);
        $Address->method('render')->willReturn('Rendered Address');
        $Address->method('getAttribute')->willReturnCallback(
            static fn(string $name): string => match ($name) {
                'contactPerson' => '  Ada Lovelace  ',
                'company' => 'Analytical Engines',
                'salutation' => 'ms',
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
                default => ''
            }
        );

        $Customer = $this->createMock(User::class);
        $Customer->method('getAddress')->willReturn($Address);
        $Customer->method('getStandardAddress')->willReturn($Address);
        $Customer->method('getName')->willReturn('Customer Name');
        $Customer->method('getAttribute')->with('email')->willReturn('customer@example.test');

        $variables = OutputProviderOrder::getCustomerVariables($Customer);

        self::assertSame('Ada Lovelace', $variables['user']);
        self::assertSame('customer@example.test', $variables['email']);
        self::assertSame('Analytical Engines', $variables['companyOrName']);
        self::assertSame('Rendered Address', $variables['address']);
    }

    public function testCustomerVariablesUseAddressFallbacks(): void
    {
        $Address = $this->createMock(Address::class);
        $Address->method('getName')->willReturn('Fallback Name');
        $Address->method('getMailList')->willReturn(['fallback@example.test']);
        $Address->method('render')->willReturn('Fallback Address');
        $Address->method('getAttribute')->willReturn('');

        $Customer = $this->createMock(User::class);
        $Customer->method('getAddress')->willReturn($Address);
        $Customer->method('getStandardAddress')->willReturn($Address);
        $Customer->method('getName')->willReturn('');
        $Customer->method('getAttribute')->with('email')->willReturn('');

        $variables = OutputProviderOrder::getCustomerVariables($Customer);

        self::assertSame('Fallback Name', $variables['user']);
        self::assertSame('fallback@example.test', $variables['email']);
        self::assertSame('', $variables['companyOrName']);
    }

    public function testOrderVariablesAndCompanyNameFallbacks(): void
    {
        $Address = $this->createMock(Address::class);
        $Address->method('getName')->willReturn('Customer Address');
        $Address->method('getMailList')->willReturn([]);
        $Address->method('render')->willReturn('Rendered');
        $Address->method('getAttribute')->willReturn('');

        $Customer = $this->createMock(User::class);
        $Customer->method('getAddress')->willReturn($Address);
        $Customer->method('getStandardAddress')->willReturn($Address);
        $Customer->method('getName')->willReturn('Customer Name');
        $Customer->method('getAttribute')->willReturn('');

        $Order = $this->createMock(Order::class);
        $Order->method('getUUID')->willReturn('order-uuid');
        $Order->method('getAttribute')->willReturnCallback(
            static fn(string $name): mixed => match ($name) {
                'hash' => 'order-hash',
                'date' => '2024-02-03',
                'contact_person' => 'Stored Contact',
                default => null
            }
        );

        $variables = $this->invoke('getOrderLocaleVar', $Order, $Customer);

        self::assertSame('order-uuid', $variables['orderId']);
        self::assertSame('order-hash', $variables['hash']);
        self::assertSame('Stored Contact', $variables['contactPerson']);
        self::assertArrayHasKey('systemCompany', $variables);
        self::assertSame('Customer Name', $this->invoke('getCompanyOrName', $Customer));
        self::assertIsString($this->invoke('getCompanyName'));
    }

    public function testQrCodeStopsForUnsupportedOrderData(): void
    {
        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('USD');
        $Order = $this->createMock(Order::class);
        $Order->method('getCurrency')->willReturn($Currency);

        self::assertFalse($this->invoke('getEpcQrCodeImageImgSrc', $Order));

        $Euro = $this->createMock(Currency::class);
        $Euro->method('getCode')->willReturn('EUR');
        $OrderWithoutPayment = $this->createMock(Order::class);
        $OrderWithoutPayment->method('getCurrency')->willReturn($Euro);
        $OrderWithoutPayment->method('getPayment')->willReturn(null);

        self::assertFalse($this->invoke('getEpcQrCodeImageImgSrc', $OrderWithoutPayment));

        $PaymentType = $this->createMock(AbstractPayment::class);
        $Payment = $this->createMock(Payment::class);
        $Payment->method('getPaymentType')->willReturn($PaymentType);
        $UnsupportedPaymentOrder = $this->createMock(Order::class);
        $UnsupportedPaymentOrder->method('getCurrency')->willReturn($Euro);
        $UnsupportedPaymentOrder->method('getPayment')->willReturn($Payment);

        self::assertFalse($this->invoke('getEpcQrCodeImageImgSrc', $UnsupportedPaymentOrder));
    }

    public function testQrCodeAcceptsAdvancePaymentObjectAndRendersEpcPayload(): void
    {
        $Config = \QUI::getPackage('quiqqer/erp')->getConfig();
        self::assertNotNull($Config);
        $originalAccounts = $Config->getValue('bankAccounts', 'accounts');
        $Config->set('bankAccounts', 'accounts', json_encode([
            901 => [
                'id' => 901,
                'title' => 'PHPUnit bank account',
                'name' => 'Example Company',
                'accountHolder' => 'Example Company',
                'iban' => 'DE89370400440532013000',
                'bic' => 'COBADEFFXXX',
                'creditorId' => '',
                'default' => 1,
                'financialAccountNo' => ''
            ]
        ]));
        $Config->save();

        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('EUR');
        $Payment = $this->createMock(Payment::class);
        $Payment->method('getPaymentType')->willReturn(new AdvancePayment());
        $Order = $this->createMock(Order::class);
        $Order->method('getCurrency')->willReturn($Currency);
        $Order->method('getPayment')->willReturn($Payment);
        $Order->method('getPaidStatusInformation')->willReturn(['toPay' => 12.34]);
        $Order->method('getUUID')->willReturn('QR-ORDER-1');

        try {
            $image = $this->invoke('getEpcQrCodeImageImgSrc', $Order);
            self::assertIsString($image);
            self::assertStringStartsWith('data:image/png;base64,', $image);
        } finally {
            $Config->set('bankAccounts', 'accounts', $originalAccounts);
            $Config->save();
        }
    }

    public function testAnonymousUserHasNoDownloadPermission(): void
    {
        self::assertFalse(OutputProviderOrder::hasDownloadPermission(
            'irrelevant',
            \QUI::getUsers()->getNobody()
        ));
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod(OutputProviderOrder::class, $method))->invoke(null, ...$arguments);
    }
}
