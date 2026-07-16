<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\ERP\ErpEntityInterface;
use QUI\ERP\Order\Mail;
use QUI\Interfaces\Users\User;
use ReflectionMethod;

class MailUnitTest extends TestCase
{
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
}
