<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\ArticleListUnique;
use QUI\ERP\Address;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Order\Controls\OrderProcess\Checkout;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderInProcess;
use QUI\Interfaces\Template\EngineInterface;
use QUI\Template;
use QUITests\ERP\Order\Fixtures\TestableCheckout;

class CheckoutUnitTest extends TestCase
{
    protected function tearDown(): void
    {
        $_REQUEST = [];
        parent::tearDown();
    }

    public function testMetadataAndMissingOrderBody(): void
    {
        $Checkout = new Checkout();

        self::assertSame('Checkout', $Checkout->getName());
        self::assertSame('fa-shopping-cart', $Checkout->getIcon());
        self::assertSame('', $Checkout->getBody());
        self::assertSame('', $Checkout->getLinkOf('phpunit_missing_site_setting'));
    }

    public function testValidationRejectsMissingOrderAndPayment(): void
    {
        $Checkout = new Checkout();

        try {
            $Checkout->validate();
            self::fail('A missing order must be rejected.');
        } catch (\QUI\ERP\Order\Exception) {
            self::assertTrue(true);
        }

        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('isSuccessful')->willReturn(0);
        $Order->method('getPayment')->willReturn(null);
        $Checkout->setAttribute('Order', $Order);

        $this->expectException(\QUI\ERP\Order\Exception::class);
        $Checkout->validate();
    }

    public function testValidationAcceptsSuccessfulAndFinalOrders(): void
    {
        $Successful = $this->createMock(OrderInProcess::class);
        $Successful->method('isSuccessful')->willReturn(1);
        $Successful->expects(self::once())->method('getPayment')->willReturn(null);
        $Checkout = new Checkout(['Order' => $Successful]);
        $Checkout->validate();

        $Payment = $this->createMock(Payment::class);
        $Final = $this->createMock(Order::class);
        $Final->method('isSuccessful')->willReturn(0);
        $Final->method('getPayment')->willReturn($Payment);
        $Checkout->setAttribute('Order', $Final);
        $Checkout->validate();

        self::assertTrue(true);
    }

    public function testValidationRequiresTermsForOrderInProcess(): void
    {
        $Payment = $this->createMock(Payment::class);
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getUUID')->willReturn('checkout-terms-test');
        $Order->method('isSuccessful')->willReturn(0);
        $Order->method('getPayment')->willReturn($Payment);
        $Checkout = new Checkout(['Order' => $Order]);
        \QUI::getSession()->del('termsAndConditions-checkout-terms-test');

        try {
            $Checkout->validate();
            self::fail('Missing terms must be rejected.');
        } catch (\QUI\ERP\Order\Exception) {
            self::assertTrue(true);
        }

        \QUI::getSession()->set('termsAndConditions-checkout-terms-test', 1);
        $Checkout->validate();
        \QUI::getSession()->del('termsAndConditions-checkout-terms-test');
        self::assertTrue(true);
    }

    public function testSaveHonoursRequestGuardsAndPersistsTerms(): void
    {
        $Payment = $this->createMock(Payment::class);
        $Payment->method('getId')->willReturn(17);
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getUUID')->willReturn('checkout-save-test');
        $Order->method('getPayment')->willReturn($Payment);
        $Order->expects(self::exactly(4))->method('setData');
        $Order->expects(self::exactly(2))->method('save');
        $Checkout = new Checkout(['Order' => $Order]);

        $Checkout->save();
        $_REQUEST = ['termsAndConditions' => '1'];
        $Checkout->save();
        self::assertSame(1, \QUI::getSession()->get('termsAndConditions-checkout-save-test'));
        $_REQUEST['current'] = 'Checkout';
        $Checkout->save();
        $_REQUEST['payableToOrder'] = '1';
        $Checkout->save();
        $Checkout->forceSave();

        \QUI::getSession()->del('termsAndConditions-checkout-save-test');
    }

    public function testForceSaveStopsWithoutOrderOrPayment(): void
    {
        (new Checkout())->forceSave();

        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getPayment')->willReturn(null);
        $Order->expects(self::never())->method('setData');
        (new Checkout(['Order' => $Order]))->forceSave();

        self::assertTrue(true);
    }

    public function testBodyRendersRecalculatedOrderAndNormalizesEmptyInvoiceAddress(): void
    {
        $originalTemplate = QUI::$Template;
        $Engine = $this->createMock(EngineInterface::class);
        $assignments = [];
        $Engine->method('assign')->willReturnCallback(
            static function (array $data) use (&$assignments): void {
                $assignments[] = $data;
            }
        );
        $Engine->expects(self::once())
            ->method('fetch')
            ->with(self::stringEndsWith('/Checkout.html'))
            ->willReturn('rendered checkout');
        $Template = $this->createMock(Template::class);
        $Template->method('getEngine')->willReturn($Engine);
        QUI::$Template = $Template;

        $UniqueArticles = $this->createMock(ArticleListUnique::class);
        $UniqueArticles->expects(self::once())->method('hideHeader');
        $Articles = $this->createMock(ArticleList::class);
        $Articles->method('toUniqueList')->willReturn($UniqueArticles);
        $InvoiceAddress = $this->createMock(Address::class);
        $InvoiceAddress->method('getName')->willReturn('');
        $InvoiceAddress->method('getPhone')->willReturn('');
        $InvoiceAddress->method('getAttribute')->willReturn(false);
        $DeliveryAddress = $this->createMock(Address::class);
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Order = $this->createMock(OrderInProcess::class);
        $Order->expects(self::once())->method('recalculate');
        $Order->method('getArticles')->willReturn($Articles);
        $Order->method('getInvoiceAddress')->willReturn($InvoiceAddress);
        $Order->method('getDeliveryAddress')->willReturn($DeliveryAddress);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getPayment')->willReturn(null);
        $Order->method('getShipping')->willReturn(null);
        $Checkout = new TestableCheckout(['Order' => $Order]);
        QUI::getSession()->set('comment-message', 'Checkout note');

        try {
            self::assertSame('rendered checkout', $Checkout->getBody());
            self::assertCount(2, $assignments);
            self::assertNull($assignments[1]['InvoiceAddress']);
            self::assertSame('Checkout note', $assignments[1]['comment']);
            self::assertSame($UniqueArticles, $assignments[1]['Articles']);
        } finally {
            QUI::getSession()->del('comment-message');
            QUI::$Template = $originalTemplate;
        }
    }

    /**
     * @param array<string, string> $links
     */
    #[DataProvider('linkProvider')]
    public function testCheckboxLinkGeneration(array $links, bool $mandatory): void
    {
        $Checkout = new TestableCheckout();
        $Checkout->links = $links;
        $Engine = $this->createMock(EngineInterface::class);
        $Engine->expects(self::once())->method('assign')->with(self::callback(
            static fn(array $data): bool => $data['hasMandatoryLinks'] === $mandatory
                && ($mandatory ? $data['acceptText'] !== '' : $data['acceptText'] === '')
        ));

        $Checkout->generateCheckboxLinks($Engine);
    }

    public static function linkProvider(): array
    {
        return [
            'none' => [[], false],
            'one' => [['terms_and_conditions' => '<a>Terms</a>'], true],
            'two' => [[
                'terms_and_conditions' => '<a>Terms</a>',
                'privacy_policy' => '<a>Privacy</a>'
            ], true],
            'three' => [[
                'terms_and_conditions' => '<a>Terms</a>',
                'privacy_policy' => '<a>Privacy</a>',
                'revocation' => '<a>Revocation</a>'
            ], true]
        ];
    }
}
