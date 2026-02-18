<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\Basket\Exception as BasketException;
use QUI\ERP\Order\Basket\ExceptionBasketNotFound;
use QUI\ERP\Order\ErpProvider;
use QUI\ERP\Order\Exception as OrderException;
use QUI\ERP\Order\PaymentReceiver;
use QUI\ERP\Order\ProcessingException;
use QUI\ERP\Order\ProcessingStatus\Exception as ProcessingStatusException;

class SimpleClassesUnitTest extends TestCase
{
    public function testPaymentReceiverTypeIdentifier(): void
    {
        $this->assertSame('Order', PaymentReceiver::getType());
    }

    public function testErpProviderReturnsOrderNumberRange(): void
    {
        $ranges = ErpProvider::getNumberRanges();

        $this->assertCount(1, $ranges);
        $this->assertInstanceOf(\QUI\ERP\Order\NumberRanges\Order::class, $ranges[0]);
    }

    public function testErpProviderMailLocaleDefinitionHasExpectedShape(): void
    {
        $mailLocale = ErpProvider::getMailLocale();

        $this->assertCount(3, $mailLocale);

        foreach ($mailLocale as $entry) {
            $this->assertArrayHasKey('title', $entry);
            $this->assertArrayHasKey('description', $entry);
            $this->assertArrayHasKey('subject', $entry);
            $this->assertArrayHasKey('content', $entry);
            $this->assertArrayHasKey('subject.description', $entry);
            $this->assertArrayHasKey('content.description', $entry);
        }
    }

    public function testPaymentReceiverTypeTitleReturnsString(): void
    {
        $title = PaymentReceiver::getTypeTitle();

        $this->assertIsString($title);
        $this->assertNotSame('', $title);
    }

    public function testStatusUnknownTitleReturnsString(): void
    {
        $Status = new \QUI\ERP\Order\ProcessingStatus\StatusUnknown();
        $title = $Status->getTitle();

        $this->assertIsString($title);
        $this->assertNotSame('', $title);
    }

    public function testCustomExceptionsCanBeCreatedWithMessage(): void
    {
        $exceptions = [
            new OrderException('order'),
            new ProcessingException('processing'),
            new BasketException('basket'),
            new ExceptionBasketNotFound('basket.not.found'),
            new ProcessingStatusException('status')
        ];

        foreach ($exceptions as $Exception) {
            $this->assertInstanceOf(\QUI\Exception::class, $Exception);
            $this->assertNotSame('', $Exception->getMessage());
        }
    }
}
