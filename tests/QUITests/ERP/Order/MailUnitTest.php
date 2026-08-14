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
use ReflectionProperty;
use QUI\Utils\Singleton;
use QUITests\ERP\Order\Fixtures\TestableHandler;

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

    public function testCustomerMailProjectResolutionUsesStoredProjectAndCustomerLanguage(): void
    {
        $originalRewrite = \QUI::$Rewrite;
        $CurrentProject = $this->createMock(\QUI\Projects\Project::class);
        $CurrentProject->method('getName')->willReturn('stored-project');
        $CurrentProject->method('getLanguages')->willReturn(['en']);
        $Rewrite = $this->createMock(\QUI\Rewrite::class);
        $Rewrite->method('getProject')->willReturn($CurrentProject);
        \QUI::$Rewrite = $Rewrite;
        $Customer = $this->createMock(ERPUser::class);
        $Customer->method('getLang')->willReturn('de');
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getAttribute')->with('project_name')->willReturn('stored-project');
        $Order->method('getCustomer')->willReturn($Customer);

        try {
            self::assertSame(
                $CurrentProject,
                $this->invokeMailMethod('getProjectForCustomerMail', $Order)
            );
            self::assertSame(
                $CurrentProject,
                (new ReflectionMethod(\QUI\ERP\Order\Handler::class, 'getProjectForCustomerMail'))
                    ->invoke(new TestableHandler(), $Order)
            );
            $StatusHandler = (new \ReflectionClass(\QUI\ERP\Order\ProcessingStatus\Handler::class))
                ->newInstanceWithoutConstructor();
            self::assertSame(
                $CurrentProject,
                (new ReflectionMethod(
                    \QUI\ERP\Order\ProcessingStatus\Handler::class,
                    'getProjectForCustomerMail'
                ))->invoke($StatusHandler, $Order)
            );
        } finally {
            \QUI::$Rewrite = $originalRewrite;
        }
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

    public function testShippingConfirmationUsesAddressEmailAndPersistsAuditEntry(): void
    {
        $originalMailManager = \QUI::$MailManager;
        $originalRewrite = \QUI::$Rewrite;
        $Locale = $this->createMock(\QUI\Locale::class);
        $Locale->method('getCurrent')->willReturn('en');
        $Locale->method('getLocalesByLang')->willReturn(['en_US']);
        $Locale->method('get')->willReturnCallback(
            static fn(string $package, string $key): string => $package . ':' . $key
        );
        $Address = $this->createMock(ERPAddress::class);
        $Address->method('getName')->willReturn('Shipping Recipient');
        $Address->method('getMailList')->willReturn(['shipping@example.test']);
        $Address->method('getAttribute')->willReturn('');
        $Address->method('render')->willReturn('Rendered shipping address');
        $Customer = $this->createMock(ERPUser::class);
        $Customer->method('getLocale')->willReturn($Locale);
        $Customer->method('getAddress')->willReturn($Address);
        $Customer->method('getStandardAddress')->willReturn($Address);
        $Customer->method('getName')->willReturn('Shipping Recipient');
        $Customer->method('getAttribute')->with('email')->willReturn('');
        $Customer->method('getLang')->willReturn('en');
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getUUID')->willReturn('shipping-order');
        $Order->method('getPrefixedNumber')->willReturn('ORDER-7');
        $Order->method('getDataEntry')->willReturnCallback(
            static fn(string $key): mixed => match ($key) {
                'shippingConfirmation' => null,
                'shippingTracking' => '{"type":"parcel"}',
                default => null
            }
        );
        $Order->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => match ($key) {
                'hash' => 'shipping-order',
                'date' => '2024-01-02',
                'project_name' => false,
                default => null
            }
        );
        $Order->expects(self::once())
            ->method('setData')
            ->with('shippingConfirmation', self::callback(
                static fn(array $entries): bool => count($entries) === 1
                    && $entries[0]['email'] === 'shipping@example.test'
            ));
        $Order->expects(self::once())->method('update');
        $Mailer = $this->createMock(\QUI\Mail\Mailer::class);
        $Mailer->expects(self::once())->method('addRecipient')->with('shipping@example.test');
        $Mailer->expects(self::once())->method('setSubject');
        $Mailer->expects(self::once())->method('setBody');
        $Mailer->expects(self::once())->method('send')->willReturn(true);
        $MailManager = $this->createMock(\QUI\Mail\Manager::class);
        $MailManager->expects(self::once())->method('getMailer')->with([])->willReturn($Mailer);
        $Rewrite = $this->createMock(\QUI\Rewrite::class);
        $Rewrite->method('getProject')->willReturn(null);
        \QUI::$MailManager = $MailManager;
        \QUI::$Rewrite = $Rewrite;

        try {
            Mail::sendOrderShippingConfirmation($Order);
            self::assertTrue(true);
        } finally {
            \QUI::$MailManager = $originalMailManager;
            \QUI::$Rewrite = $originalRewrite;
        }
    }

    public function testShippingConfirmationSendFailureDoesNotPersist(): void
    {
        $originalMailManager = \QUI::$MailManager;
        $originalRewrite = \QUI::$Rewrite;
        $Address = $this->createMock(ERPAddress::class);
        $Address->method('getMailList')->willReturn([]);
        $Address->method('getAttribute')->willReturn('');
        $Address->method('render')->willReturn('Address');
        $Customer = $this->createMock(ERPUser::class);
        $Customer->method('getLocale')->willReturn($this->createLocale());
        $Customer->method('getAddress')->willReturn($Address);
        $Customer->method('getStandardAddress')->willReturn($Address);
        $Customer->method('getName')->willReturn('Customer');
        $Customer->method('getAttribute')->with('email')->willReturn('direct@example.test');
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getUUID')->willReturn('failed-shipping-order');
        $Order->method('getPrefixedNumber')->willReturn('ORDER-8');
        $Order->method('getDataEntry')->willReturn(null);
        $Order->method('getAttribute')->willReturn(null);
        $Order->expects(self::never())->method('setData');
        $Order->expects(self::never())->method('update');
        $Mailer = $this->createMock(\QUI\Mail\Mailer::class);
        $Mailer->method('send')->willThrowException(new \QUI\Exception('mail failed'));
        $MailManager = $this->createMock(\QUI\Mail\Manager::class);
        $MailManager->method('getMailer')->willReturn($Mailer);
        $Rewrite = $this->createMock(\QUI\Rewrite::class);
        $Rewrite->method('getProject')->willReturn(null);
        \QUI::$MailManager = $MailManager;
        \QUI::$Rewrite = $Rewrite;

        try {
            Mail::sendOrderShippingConfirmation($Order);
            self::assertTrue(true);
        } finally {
            \QUI::$MailManager = $originalMailManager;
            \QUI::$Rewrite = $originalRewrite;
        }
    }

    public function testCustomerOrderConfirmationBuildsAndSendsCompleteMail(): void
    {
        $originalMailManager = \QUI::$MailManager;
        $originalRewrite = \QUI::$Rewrite;
        $Address = $this->createMock(ERPAddress::class);
        $Address->method('getName')->willReturn('Customer Name');
        $Address->method('getMailList')->willReturn(['customer@example.test']);
        $Address->method('getAttribute')->willReturn('');
        $Address->method('render')->willReturn('Rendered customer address');
        $Customer = $this->createMock(ERPUser::class);
        $Customer->method('getLocale')->willReturn($this->createLocale());
        $Customer->method('getAddress')->willReturn($Address);
        $Customer->method('getStandardAddress')->willReturn($Address);
        $Customer->method('getName')->willReturn('Customer Name');
        $Customer->method('getAttribute')->with('email')->willReturn('customer@example.test');
        $Customer->method('getUUID')->willReturn('mail-customer');
        $Articles = $this->createMock(\QUI\ERP\Accounting\ArticleList::class);
        $UniqueArticles = $this->createMock(\QUI\ERP\Accounting\ArticleListUnique::class);
        $Articles->method('toUniqueList')->willReturn($UniqueArticles);
        $UniqueArticles->expects(self::once())->method('hideHeader');
        $InvoiceAddress = $this->createMock(ERPAddress::class);
        $Order = $this->createMock(Order::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getUUID')->willReturn('mail-order');
        $Order->method('getPrefixedNumber')->willReturn('ORDER-9');
        $Order->method('getArticles')->willReturn($Articles);
        $Order->method('getInvoiceAddress')->willReturn($InvoiceAddress);
        $Order->method('getShipping')->willReturn(null);
        $Order->method('getPayment')->willReturn(null);
        $Order->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => match ($key) {
                'hash' => 'mail-order',
                'date' => '2024-01-02',
                'project_name' => false,
                default => null
            }
        );
        $Mailer = $this->createMock(\QUI\Mail\Mailer::class);
        $Mailer->expects(self::once())->method('addRecipient')->with('customer@example.test');
        $Mailer->expects(self::once())->method('setSubject');
        $Mailer->expects(self::once())->method('setBody');
        $Mailer->expects(self::once())->method('send')->willReturn(true);
        $MailManager = $this->createMock(\QUI\Mail\Manager::class);
        $MailManager->method('getMailer')->willReturn($Mailer);
        $Rewrite = $this->createMock(\QUI\Rewrite::class);
        $Rewrite->method('getProject')->willReturn(null);
        $Handler = new TestableHandler();
        $Handler->setResolvedOrder($Order);
        \QUI::$MailManager = $MailManager;
        \QUI::$Rewrite = $Rewrite;

        try {
            $this->withHandler($Handler, static function () use ($Order): void {
                Mail::sendOrderConfirmationMail($Order);
            });
        } finally {
            \QUI::$MailManager = $originalMailManager;
            \QUI::$Rewrite = $originalRewrite;
        }
    }

    public function testAdminOrderConfirmationBuildsAndSendsCompleteMail(): void
    {
        $originalMailManager = \QUI::$MailManager;
        $Config = \QUI\ERP\Order\Settings::getConfig();
        $originalAdminMails = $Config->getValue('order', 'orderAdminMails');
        $Config->set('order', 'orderAdminMails', 'admin@example.test');
        $Config->save();
        $Address = $this->createMock(ERPAddress::class);
        $Address->method('getName')->willReturn('Admin Customer');
        $Address->method('getMailList')->willReturn(['customer@example.test']);
        $Address->method('getAttribute')->willReturn('');
        $Address->method('render')->willReturn('Rendered address');
        $Customer = $this->createMock(ERPUser::class);
        $Customer->method('getLocale')->willReturn($this->createLocale());
        $Customer->method('getAddress')->willReturn($Address);
        $Customer->method('getStandardAddress')->willReturn($Address);
        $Customer->method('getName')->willReturn('');
        $Customer->method('getUUID')->willReturn('admin-mail-customer');
        $Customer->method('getAttribute')->willReturn('customer@example.test');
        $Articles = $this->createMock(\QUI\ERP\Accounting\ArticleList::class);
        $UniqueArticles = $this->createMock(\QUI\ERP\Accounting\ArticleListUnique::class);
        $Articles->method('toUniqueList')->willReturn($UniqueArticles);
        $UniqueArticles->expects(self::once())->method('hideHeader');
        $Order = $this->createMock(Order::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getUUID')->willReturn('admin-mail-order');
        $Order->method('getPrefixedNumber')->willReturn('ORDER-10');
        $Order->method('getComments')->willReturn(new \QUI\ERP\Comments());
        $Order->method('getArticles')->willReturn($Articles);
        $Order->method('getInvoiceAddress')->willReturn($Address);
        $Order->method('getShipping')->willReturn(null);
        $Order->method('getPayment')->willReturn(null);
        $Order->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => match ($key) {
                'hash' => 'admin-mail-order',
                'date' => '2024-01-02',
                default => null
            }
        );
        $Mailer = $this->createMock(\QUI\Mail\Mailer::class);
        $Mailer->expects(self::once())->method('addRecipient')->with('admin@example.test');
        $Mailer->expects(self::once())->method('setSubject');
        $Mailer->expects(self::once())->method('setBody');
        $Mailer->expects(self::once())->method('send')->willReturn(true);
        $MailManager = $this->createMock(\QUI\Mail\Manager::class);
        $MailManager->method('getMailer')->willReturn($Mailer);
        $Handler = new TestableHandler();
        $Handler->setResolvedOrder($Order);
        \QUI::$MailManager = $MailManager;

        try {
            $this->withHandler($Handler, static function () use ($Order): void {
                Mail::sendAdminOrderConfirmationMail($Order);
            });
        } finally {
            $Config->set('order', 'orderAdminMails', $originalAdminMails);
            $Config->save();
            \QUI::$MailManager = $originalMailManager;
        }
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

    private function withHandler(TestableHandler $Handler, callable $callback): void
    {
        $Instances = new ReflectionProperty(Singleton::class, 'instances');
        $original = $Instances->getValue();
        $instances = $original;
        $instances[\QUI\ERP\Order\Handler::class] = $Handler;
        $Instances->setValue(null, $instances);

        try {
            $callback();
        } finally {
            $Instances->setValue(null, $original);
        }
    }
}
