<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\ArticleListUnique;
use QUI\ERP\Address;
use QUI\ERP\Comments;
use QUI\ERP\Order\Handler;
use QUI\ERP\Order\Mail;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\Settings;
use QUI\ERP\Shipping\Shipping;
use QUI\ERP\Shipping\Types\ShippingEntry;
use QUI\ERP\Shipping\Types\ShippingUnique;
use QUI\ERP\User;
use QUI\Interfaces\Template\EngineInterface;
use QUI\Utils\Singleton;
use QUITests\ERP\Order\Fixtures\TestableHandler;
use ReflectionMethod;
use ReflectionProperty;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ShippingAddressFallbackTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $previousInstances;
    private ?QUI\Template $PreviousTemplate;
    private ?QUI\Mail\Manager $PreviousMailManager;
    private ?QUI\Rewrite $PreviousRewrite;
    /** @var array<string, array<string, mixed>> */
    private array $previousSettings;

    protected function setUp(): void
    {
        parent::setUp();

        // The shipping package is optional. Keep these fallback types confined to this test process.
        if (!class_exists(Shipping::class)) {
            require_once dirname(__DIR__, 3) . '/stubs/QUI/ERP/Shipping/Shipping.php';
        }

        if (!class_exists(ShippingUnique::class)) {
            require_once dirname(__DIR__, 3) . '/stubs/QUI/ERP/Shipping/Types/ShippingUnique.php';
        }

        $this->previousInstances = (new ReflectionProperty(Singleton::class, 'instances'))->getValue();
        $this->PreviousTemplate = QUI::$Template;
        $this->PreviousMailManager = QUI::$MailManager;
        $this->PreviousRewrite = QUI::$Rewrite;
        $Config = Settings::getConfig();
        $this->previousSettings = $Config->toArray();
        $Config->setValue('order', 'orderAdminMails', 'admin@example.test');

        foreach (['privacyPolicy', 'termsAndConditions', 'cancellationPolicy', 'attachments'] as $setting) {
            $Config->setValue('mails', $setting, '');
        }
    }

    protected function tearDown(): void
    {
        $Config = Settings::getConfig();

        $changedSettings = [
            'order' => ['orderAdminMails'],
            'mails' => [
                'privacyPolicy',
                'termsAndConditions',
                'cancellationPolicy',
                'attachments'
            ]
        ];

        foreach ($changedSettings as $section => $settings) {
            foreach ($settings as $setting) {
                if (array_key_exists($setting, $this->previousSettings[$section] ?? [])) {
                    $Config->setValue($section, $setting, $this->previousSettings[$section][$setting]);
                } else {
                    $Config->del($section, $setting);
                }
            }
        }

        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, $this->previousInstances);
        QUI::$Template = $this->PreviousTemplate;
        QUI::$MailManager = $this->PreviousMailManager;
        QUI::$Rewrite = $this->PreviousRewrite;
        parent::tearDown();
    }

    /** @return iterable<string, array{string}> */
    public static function shippingStates(): iterable
    {
        yield 'no shipping selected' => ['none'];
        yield 'shipping lookup returns null' => ['missing'];
        yield 'stored shipping has no address API' => ['stored'];
        yield 'shipping address is null' => ['no-address'];
        yield 'shipping address is available' => ['address'];
    }

    /** @return iterable<string, array{string, bool}> */
    public static function mailScenarios(): iterable
    {
        foreach (self::shippingStates() as $name => [$state]) {
            yield 'customer: ' . $name => [$state, false];
            yield 'admin: ' . $name => [$state, true];
        }
    }

    #[DataProvider('mailScenarios')]
    public function testConfirmationMailHandlesMissingShippingAndAddresses(string $state, bool $admin): void
    {
        [$Order, $ExistingAddress, $ShippingAddress] = $this->createOrder($state);
        $ExpectedAddress = match ($state) {
            'none' => null,
            'address' => $ShippingAddress,
            default => $ExistingAddress
        };
        $variables = [];
        $Engine = $this->createMock(EngineInterface::class);
        $Engine->method('assign')->willReturnCallback(
            static function (array|string $name, mixed $value = false) use (&$variables): void {
                if (is_array($name)) {
                    $variables = array_replace($variables, $name);
                } else {
                    $variables[$name] = $value;
                }
            }
        );
        $Engine->method('fetch')->willReturn('<p>Order confirmation</p>');
        $Template = $this->createMock(QUI\Template::class);
        $Template->method('getEngine')->willReturn($Engine);
        QUI::$Template = $Template;
        $Mailer = $this->createMock(QUI\Mail\Mailer::class);
        $Mailer->expects(self::once())->method('setBody')->with('<p>Order confirmation</p>');
        $Mailer->expects(self::once())->method('send')->willReturn(true);
        $MailManager = $this->createMock(QUI\Mail\Manager::class);
        $MailManager->method('getMailer')->willReturn($Mailer);
        QUI::$MailManager = $MailManager;
        $Rewrite = $this->createMock(QUI\Rewrite::class);
        $Rewrite->method('getProject')->willReturn(null);
        QUI::$Rewrite = $Rewrite;

        if ($admin) {
            Mail::sendAdminOrderConfirmationMail($Order);
        } else {
            Mail::sendOrderConfirmationMail($Order);
        }

        self::assertArrayHasKey('DeliveryAddress', $variables);
        self::assertSame($ExpectedAddress, $variables['DeliveryAddress']);

        if ($ExpectedAddress !== null) {
            self::assertStringEndsWith('orderConfirmationAddress.html', $ExpectedAddress->getAttribute('template'));
        }
    }

    #[DataProvider('shippingStates')]
    public function testSavingRetainsDeliveryAddressWhenShippingHasNoAddress(string $state): void
    {
        [$Order, $ExistingAddress, $ShippingAddress] = $this->createOrder($state);
        $data = (new ReflectionMethod(Order::class, 'getDataForSaving'))->invoke($Order);
        $ExpectedAddress = $state === 'address' ? $ShippingAddress : $ExistingAddress;

        self::assertSame($ExpectedAddress->toJSON(), $data['addressDelivery']);
    }

    /** @return array{Order, Address, Address} */
    private function createOrder(string $state): array
    {
        $ExistingAddress = new Address(['uuid' => 'saved-address', 'city' => 'Saved delivery city']);
        $ShippingAddress = new Address(['uuid' => 'shipping-address', 'city' => 'Shipping delivery city']);
        $InvoiceAddress = new Address(['uuid' => 'invoice-address', 'city' => 'Invoice city']);
        $ShippingEntry = $this->createMock(ShippingEntry::class);
        $ShippingEntry->method('getAddress')->willReturn($state === 'address' ? $ShippingAddress : null);
        $ShippingEntry->method('getId')->willReturn(17);
        $ShippingEntry->method('toJSON')->willReturn('{}');
        $Customer = $this->createMock(User::class);
        $Customer->method('getName')->willReturn('Test Customer');
        $Customer->method('getUUID')->willReturn('test-customer');
        $Customer->method('getLang')->willReturn('en');
        $Customer->method('getLocale')->willReturn(QUI::getLocale());
        $Customer->method('getStandardAddress')->willReturn($InvoiceAddress);
        $Customer->method('getAttributes')->willReturn([]);
        $Customer->method('getAttribute')->willReturnCallback(
            static fn (string $name): string => $name === 'email' ? 'customer@example.test' : ''
        );
        $Articles = $this->createMock(ArticleList::class);
        $Articles->method('toUniqueList')->willReturn($this->createMock(ArticleListUnique::class));
        $Articles->method('toJSON')->willReturn('[]');
        $Order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getCustomer',
                'getArticles',
                'getInvoiceAddress',
                'getDeliveryAddress',
                'getShipping',
                'getShippingStatus',
                'getPayment',
                'getCurrency',
                'hasInvoice',
                'getPrefixedNumber',
                'getUUID',
                'getCreateDate'
            ])
            ->getMock();
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getArticles')->willReturn($Articles);
        $Order->method('getInvoiceAddress')->willReturn($InvoiceAddress);
        $Order->method('getDeliveryAddress')->willReturn($ExistingAddress);
        $Order->method('getShipping')->willReturn($state === 'none' ? null : $ShippingEntry);
        $Order->method('getShippingStatus')->willReturn(null);
        $Order->method('getPayment')->willReturn(null);
        $Order->method('getCurrency')->willReturn(QUI\ERP\Defaults::getCurrency());
        $Order->method('hasInvoice')->willReturn(false);
        $Order->method('getPrefixedNumber')->willReturn('ORDER-SHIPPING-TEST');
        $Order->method('getUUID')->willReturn('shipping-address-test');
        $Order->method('getCreateDate')->willReturn('2026-11-01 09:00:00');

        $properties = [
            'idPrefix' => 'ORDER-',
            'successful' => 1,
            'customerId' => 'test-customer',
            'data' => [],
            'Articles' => $Articles,
            'Comments' => new Comments(),
            'History' => new Comments(),
            'StatusMails' => new Comments(),
            'FrontendMessage' => new Comments()
        ];

        foreach ($properties as $property => $value) {
            (new ReflectionProperty($Order, $property))->setValue($Order, $value);
        }

        $Handler = new TestableHandler();
        $Handler->setResolvedOrder($Order);
        $Shipping = $this->createMock(Shipping::class);
        $resolvedShipping = match ($state) {
            'none', 'missing' => null,
            'stored' => $this->createMock(ShippingUnique::class),
            default => $ShippingEntry
        };
        $Shipping->expects($state === 'none' ? self::never() : self::once())
            ->method('getShippingByObject')
            ->with($Order)
            ->willReturn($resolvedShipping);
        $instances = $this->previousInstances;
        $instances[Handler::class] = $Handler;
        $instances[Shipping::class] = $Shipping;
        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, $instances);

        return [$Order, $ExistingAddress, $ShippingAddress];
    }
}
