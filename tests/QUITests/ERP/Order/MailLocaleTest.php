<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\ArticleListUnique;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Address;
use QUI\ERP\Order\Handler;
use QUI\ERP\Order\Mail;
use QUI\ERP\Order\Order;
use QUI\ERP\User;
use QUI\Locale;
use QUI\Projects\Manager;
use QUI\Projects\Project;
use QUI\Utils\Singleton;
use QUITests\ERP\Order\Fixtures\TestableHandler;
use ReflectionProperty;
use RuntimeException;

class MailLocaleTest extends TestCase
{
    public static function languages(): iterable
    {
        yield 'supported English customer' => ['en', 'de', ['en', 'de'], 'en', false, ''];
        yield 'supported German customer' => ['de', 'en', ['en', 'de'], 'de', false, ''];
        yield 'unsupported German customer' => ['de', 'en', ['en'], 'en', false, ''];
        yield 'project language, not its default' => ['fr', 'de', ['en', 'de'], 'de', false, ''];
        yield 'backend resend uses stored project' => ['en', 'de', ['en', 'de'], 'en', true, ''];
        yield 'render error restores language' => ['en', 'de', ['en', 'de'], 'en', false, 'render'];
        yield 'send error restores language' => ['en', 'de', ['en', 'de'], 'en', false, 'send'];
    }

    /** @param list<string> $languages */
    #[DataProvider('languages')]
    public function testConfirmationUsesOneLanguageWithoutChangingTheOrderOrCaller(
        string $customerLanguage,
        string $projectLanguage,
        array $languages,
        string $expectedLanguage,
        bool $backend,
        string $failure
    ): void {
        $originalLanguage = QUI::getLocale()->getCurrent();
        $originalRewrite = QUI::$Rewrite;
        $originalMailManager = QUI::$MailManager;
        $originalProjects = Manager::$projects;
        $Instances = new ReflectionProperty(Singleton::class, 'instances');
        $originalInstances = $Instances->getValue();
        $callerLanguage = $expectedLanguage === 'en' ? 'de' : 'en';
        $ExpectedLocale = $this->locale($expectedLanguage);
        $CustomerLocale = $this->locale($customerLanguage);

        $projects = [];

        foreach ($languages as $language) {
            $Project = $this->createMock(Project::class);
            $Project->method('getName')->willReturn('mail-locale-test');
            $Project->method('getLang')->willReturn($language);
            $Project->method('getDefaultLang')->willReturn('en');
            $Project->method('getLanguages')->willReturn($languages);
            $projects[$language] = $Project;
        }

        $projects['_standard'] = $projects[$projectLanguage];
        $Rewrite = $this->createMock(QUI\Rewrite::class);
        $Rewrite->method('getProject')->willReturn($backend ? null : $projects[$projectLanguage]);
        $Address = $this->createMock(Address::class);
        $Address->method('getAttribute')->willReturn('');
        $Address->method('render')->willReturnCallback(
            static fn(): string => 'address-language-' . QUI::getLocale()->getCurrent()
        );
        $Customer = $this->createMock(User::class);
        $Customer->method('getLocale')->willReturn($CustomerLocale);
        $Customer->method('getLang')->willReturn($customerLanguage);
        $Customer->method('getAddress')->willReturn($Address);
        $Customer->method('getStandardAddress')->willReturn($Address);
        $Customer->method('getName')->willReturn('Mail Customer');
        $Customer->method('getAttribute')->willReturn('customer@example.test');
        $Customer->method('getUUID')->willReturn('mail-locale-customer');
        $UniqueArticles = new ArticleListUnique($this->snapshot());
        $UniqueArticles->setLocale($this->locale($callerLanguage));
        $savedArticles = $UniqueArticles->serialize();
        $Articles = $this->createMock(ArticleList::class);

        if ($failure === 'render') {
            $Articles->method('toUniqueList')->willThrowException(new RuntimeException('Mail render failed'));
        } else {
            $Articles->method('toUniqueList')->willReturn($UniqueArticles);
        }

        $Payment = $this->createMock(Payment::class);
        $Payment->method('getTitle')->willReturn('Test payment');
        $Payment->method('getOrderInformationText')->willReturnCallback(
            static fn(): string => 'payment-language-' . QUI::getLocale()->getCurrent()
        );
        $Order = $this->createMock(Order::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getUUID')->willReturn('mail-locale-order');
        $Order->method('getPrefixedNumber')->willReturn('ORDER-158');
        $Order->method('getArticles')->willReturn($Articles);
        $Order->method('getInvoiceAddress')->willReturn($Address);
        $Order->method('getPayment')->willReturn($Payment);
        $Order->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => match ($key) {
                'project_name' => 'mail-locale-test',
                'hash' => 'mail-locale-order',
                'date' => '2024-01-02',
                default => null
            }
        );
        $Order->expects(self::never())->method('update');
        $Mailer = $this->createMock(QUI\Mail\Mailer::class);
        $Mailer->expects(self::once())->method('setSubject')->with(self::callback(
            static function (string $subject) use ($ExpectedLocale): bool {
                self::assertSame(
                    $ExpectedLocale->get('quiqqer/order', 'order.confirmation.subject', [
                        'orderId' => 'mail-locale-order', 'orderPrefixedId' => 'ORDER-158'
                    ]),
                    $subject
                );
                return true;
            }
        ));
        $Mailer->expects($failure === 'render' ? self::never() : self::once())->method('setBody')->with(self::callback(
            static function (string $html) use ($ExpectedLocale, $expectedLanguage): bool {
                foreach (['invoice.title', 'payment.title', 'details'] as $key) {
                    self::assertStringContainsString(
                        $ExpectedLocale->get('quiqqer/order', 'mail.order.confirmation.' . $key),
                        $html
                    );
                }

                self::assertStringContainsString(
                    $ExpectedLocale->get('quiqqer/erp', 'article.list.articles.subtotal'),
                    $html
                );
                self::assertStringContainsString(
                    $ExpectedLocale->get('quiqqer/tax', 'message.vat.text.netto', ['vat' => 19]),
                    $html
                );
                self::assertStringContainsString('address-language-' . $expectedLanguage, $html);
                self::assertStringContainsString('payment-language-' . $expectedLanguage, $html);
                self::assertStringContainsString('Saved article title', $html);
                return true;
            }
        ));
        $Mailer->expects($failure === 'render' ? self::never() : self::once())->method('send')->willReturnCallback(
            static function () use ($expectedLanguage, $failure): bool {
                self::assertSame($expectedLanguage, QUI::getLocale()->getCurrent());

                if ($failure === 'send') {
                    throw new RuntimeException('Mail send failed');
                }

                return true;
            }
        );
        $MailManager = $this->createMock(QUI\Mail\Manager::class);
        $MailManager->expects(self::once())->method('getMailer')
            ->with(['Project' => $projects[$expectedLanguage]])->willReturn($Mailer);
        $Handler = new TestableHandler();
        $Handler->setResolvedOrder($Order);

        try {
            QUI::getLocale()->setCurrent($callerLanguage);
            QUI::$Rewrite = $Rewrite;
            QUI::$MailManager = $MailManager;
            Manager::$projects['mail-locale-test'] = $projects;
            $instances = $originalInstances;
            $instances[Handler::class] = $Handler;
            $Instances->setValue(null, $instances);

            try {
                Mail::sendOrderConfirmationMail($Order);
                self::assertSame('', $failure, 'Expected a rendering or delivery exception.');
            } catch (RuntimeException $Exception) {
                if ($failure === '' || $Exception->getMessage() !== 'Mail ' . $failure . ' failed') {
                    throw $Exception;
                }
            }

            self::assertSame($callerLanguage, QUI::getLocale()->getCurrent());
            self::assertSame($customerLanguage, $CustomerLocale->getCurrent());
            self::assertSame($savedArticles, $UniqueArticles->serialize());
        } finally {
            QUI::getLocale()->setCurrent($originalLanguage);
            QUI::$Rewrite = $originalRewrite;
            QUI::$MailManager = $originalMailManager;
            Manager::$projects = $originalProjects;
            $Instances->setValue(null, $originalInstances);
        }
    }

    private function locale(string $language): Locale
    {
        $Locale = new Locale();
        $Locale->setCurrent($language);
        return $Locale;
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'articles' => [[
                'id' => 158, 'uuid' => 'mail-article', 'articleNo' => 'LANG-158',
                'title' => 'Saved article title', 'unitPrice' => 10, 'quantity' => 1, 'vat' => 19,
                'calculated' => [
                    'price' => 10.0, 'basisPrice' => 10.0, 'nettoPriceNotRounded' => 10.0,
                    'sum' => 11.9, 'nettoPrice' => 10.0, 'nettoBasisPrice' => 10.0,
                    'nettoSubSum' => 10.0, 'nettoSum' => 10.0,
                    'vatArray' => ['vat' => 19, 'sum' => 1.9], 'isEuVat' => false, 'isNetto' => true
                ]
            ]],
            'calculations' => [
                'sum' => 11.9, 'subSum' => 10.0, 'grandSubSum' => 11.9,
                'nettoSum' => 10.0, 'nettoSubSum' => 10.0,
                'vatArray' => ['19' => ['vat' => 19, 'sum' => 1.9, 'text' => 'Stored tax text']],
                'vatText' => ['19' => 'Stored tax text'], 'isNetto' => true, 'isEuVat' => false,
                'currencyData' => QUI\ERP\Defaults::getCurrency()->toArray()
            ]
        ];
    }
}
