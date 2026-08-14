<?php

namespace QUITests\ERP\Order;

use IntlDateFormatter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Calculations;
use QUI\ERP\Address;
use QUI\ERP\Comments;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\OrderView;
use QUI\ERP\Shipping\Api\ShippingInterface;
use QUI\ERP\Accounting\Payments\Api\AbstractPayment;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Accounting\Payments\Transactions\Handler as TransactionHandler;
use QUI\Utils\Singleton;
use QUI\ERP\User;
use ReflectionProperty;

class OrderViewUnitTest extends TestCase
{
    public function testReadAccessorsDelegateToUnderlyingOrder(): void
    {
        [$View, $Order, $Articles, $Currency] = $this->createView();
        $Comments = new Comments();
        $History = new Comments();
        $FrontendMessages = new Comments();
        $Customer = $this->createMock(User::class);
        $InvoiceAddress = $this->createMock(Address::class);
        $DeliveryAddress = $this->createMock(Address::class);
        $Calculations = $this->createMock(Calculations::class);

        $Order->method('toArray')->willReturn(['id' => 17]);
        $Order->method('getUUID')->willReturn('order-hash');
        $Order->method('getId')->willReturn(17);
        $Order->method('getPrefixedNumber')->willReturn('ORD-17');
        $Order->method('getProcessingStatus')->willReturn(null);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getComments')->willReturn($Comments);
        $Order->method('getHistory')->willReturn($History);
        $Order->method('getTransactions')->willReturn([]);
        $Order->method('getShippingStatus')->willReturn(null);
        $Order->method('isSuccessful')->willReturn(1);
        $Order->method('isPosted')->willReturn(true);
        $Order->method('getData')->willReturn(['reference' => 'ABC']);
        $Order->method('getDataEntry')->willReturnMap([
            ['reference', 'ABC'],
            ['missing', null]
        ]);
        $Order->method('getPriceCalculation')->willReturn($Calculations);
        $Order->method('getDeliveryAddress')->willReturn($DeliveryAddress);
        $Order->method('hasDeliveryAddress')->willReturn(true);
        $Order->method('getPayment')->willReturn(null);
        $Order->method('getPaidStatusInformation')->willReturn(['status' => 'paid']);
        $Order->method('isPaid')->willReturn(true);
        $Order->method('getInvoiceAddress')->willReturn($InvoiceAddress);
        $Order->method('getInvoiceType')->willReturn('Invoice');
        $Order->method('hasInvoice')->willReturn(false);
        $Order->method('getShipping')->willReturn(null);
        $Order->method('getFrontendMessages')->willReturn($FrontendMessages);
        $Articles->method('count')->willReturn(3);

        self::assertSame(['id' => 17], $View->toArray());
        self::assertSame('order-hash', $View->getHash());
        self::assertSame('order-hash', $View->getUUID());
        self::assertSame(17, $View->getId());
        self::assertSame(17, $View->getCleanedId());
        self::assertSame('ORD-17', $View->getPrefixedNumber());
        self::assertSame('ORD-17', $View->getPrefixedId());
        self::assertNull($View->getProcessingStatus());
        self::assertSame($Customer, $View->getCustomer());
        self::assertSame($Comments, $View->getComments());
        self::assertSame($History, $View->getHistory());
        self::assertSame($Currency, $View->getCurrency());
        self::assertSame([], $View->getTransactions());
        self::assertNull($View->getShippingStatus());
        self::assertSame(1, $View->isSuccessful());
        self::assertTrue($View->isPosted());
        self::assertSame(['reference' => 'ABC'], $View->getData());
        self::assertSame('ABC', $View->getDataEntry('reference'));
        self::assertNull($View->getDataEntry('missing'));
        self::assertSame($Calculations, $View->getPriceCalculation());
        self::assertSame($DeliveryAddress, $View->getDeliveryAddress());
        self::assertTrue($View->hasDeliveryAddress());
        self::assertNull($View->getPayment());
        self::assertSame(['status' => 'paid'], $View->getPaidStatusInformation());
        self::assertTrue($View->isPaid());
        self::assertSame($InvoiceAddress, $View->getInvoiceAddress());
        self::assertSame('Invoice', $View->getInvoiceType());
        self::assertFalse($View->hasInvoice());
        self::assertSame(3, $View->count());
        self::assertSame($Articles, $View->getArticles());
        self::assertNull($View->getShipping());
        self::assertSame($FrontendMessages, $View->getFrontendMessages());
        self::assertSame('', $View->getTransactionText());
    }

    public function testInvalidCreationDateReturnsEmptyValues(): void
    {
        [$View, $Order] = $this->createView();
        $Order->method('getCreateDate')->willReturn('not-a-date');

        self::assertSame('', $View->getCreateDate());
        self::assertFalse($View->getDate(QUI::getLocale()));
    }

    public function testInvoiceExceptionIsConvertedToNull(): void
    {
        [$View, $Order] = $this->createView();
        $Order->method('getInvoice')->willThrowException(new QUI\Exception('Invoice unavailable'));

        self::assertNull($View->getInvoice());
    }

    public function testArticlePreviewCombinesStylesheetAndArticleHtml(): void
    {
        [$View, , $Articles] = $this->createView();
        $Articles->method('toHTML')->willReturn('<article>Preview</article>');

        $html = $View->previewOnlyArticles();

        self::assertStringStartsWith('<style>', $html);
        self::assertStringContainsString('</style><article>Preview</article>', $html);
    }

    public function testValidCreationDateUsesProvidedLocaleFormatter(): void
    {
        [$View, $Order] = $this->createView();
        $date = '2024-01-02 12:00:00';
        $timestamp = strtotime($date);

        self::assertIsInt($timestamp);

        $Formatter = new IntlDateFormatter(
            'en_US',
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE,
            'UTC'
        );
        $Locale = $this->createMock(QUI\Locale::class);
        $Locale->method('getDateFormatter')->willReturn($Formatter);
        $Order->method('getCreateDate')->willReturn($date);

        self::assertSame($Formatter->format($timestamp), $View->getDate($Locale));
    }

    public function testMutationMethodsLeaveUnderlyingOrderUntouched(): void
    {
        require_once dirname(__DIR__, 3)
            . '/phpstan-stubs/QUI/ERP/Shipping/Api/ShippingInterface.php';

        [$View, $Order] = $this->createView();
        $Shipping = $this->createMock(ShippingInterface::class);

        $Order->expects(self::never())->method('setShipping');
        $Order->expects(self::never())->method('removeShipping');
        $Order->expects(self::never())->method('addFrontendMessage');
        $Order->expects(self::never())->method('clearFrontendMessages');

        $View->setShipping($Shipping);
        $View->removeShipping();
        $View->addFrontendMessage('message');
        $View->clearFrontendMessages();

        self::assertTrue(true);
    }

    public function testDocumentRenderingFailurePathsReturnStrings(): void
    {
        [$View, $Order] = $this->createView();
        $Order->method('getUUID')->willReturn('missing-output-order');

        self::assertIsString($View->previewHTML());
        self::assertIsString($View->toHTML());
    }

    public function testTransactionTextUsesPaymentDeadlineWithoutTransactions(): void
    {
        [$View, $Order] = $this->createView();
        $PaymentType = $this->createMock(AbstractPayment::class);
        $Payment = $this->createMock(Payment::class);
        $Payment->method('getPaymentType')->willReturn($PaymentType);
        $Customer = $this->createMock(User::class);
        $Customer->method('getLocale')->willReturn(QUI::getLocale());
        $Order->method('getPayment')->willReturn($Payment);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getUUID')->willReturn('transaction-text-order');
        $Order->method('getAttribute')->with('time_for_payment')->willReturn('tomorrow');
        $Handler = $this->createMock(TransactionHandler::class);
        $Handler->method('getTransactionsByHash')->with('transaction-text-order')->willReturn([]);
        $Instances = new ReflectionProperty(Singleton::class, 'instances');
        $original = $Instances->getValue();
        $instances = $original;
        $instances[TransactionHandler::class] = $Handler;
        $Instances->setValue(null, $instances);

        try {
            self::assertIsString($View->getTransactionText());
        } finally {
            $Instances->setValue(null, $original);
        }
    }

    /**
     * @return array{OrderView, AbstractOrder&MockObject, ArticleList&MockObject, Currency&MockObject}
     */
    private function createView(): array
    {
        $Currency = $this->createMock(Currency::class);
        $Articles = $this->createMock(ArticleList::class);
        $Articles->expects(self::once())->method('setCurrency')->with($Currency);

        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getArticles')->willReturn($Articles);
        $Order->method('getCurrency')->willReturn($Currency);
        $Order->method('getAttributes')->willReturn([]);

        return [new OrderView($Order), $Order, $Articles, $Currency];
    }
}
