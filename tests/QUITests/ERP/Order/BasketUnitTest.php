<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\PriceFactors\FactorList;
use QUI\ERP\Address;
use QUI\ERP\Comments;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Basket\Basket;
use QUI\ERP\Products\Product\ProductList;
use QUI\ERP\Products\Product\ProductListFrontendView;
use QUI\ERP\Products\Utils\PriceFactors;
use ReflectionClass;
use ReflectionProperty;

class BasketUnitTest extends TestCase
{
    public function testBasicStateClearAndSuccessfulLifecycle(): void
    {
        $List = $this->createMock(ProductList::class);
        $List->expects(self::exactly(2))->method('clear');
        $List->method('count')->willReturn(3);
        $Basket = $this->createBasket($List);
        $this->setProperty($Basket, 'id', 17);
        $this->setProperty($Basket, 'hash', 'order-hash');

        self::assertSame(17, $Basket->getId());
        self::assertSame(3, $Basket->count());
        self::assertSame($List, $Basket->getProducts());
        self::assertSame('order-hash', $Basket->getHash());

        $Basket->clear();
        $Basket->successful();

        self::assertNull($Basket->getHash());
    }

    public function testMissingOrderHashIsRejected(): void
    {
        $Basket = $this->createBasket($this->createMock(ProductList::class));

        self::assertFalse($Basket->hasOrder());
        $this->expectException(\QUI\Exception::class);
        $Basket->getOrder();
    }

    public function testSetHashAndFrontendMessages(): void
    {
        $Basket = $this->createBasket($this->createMock(ProductList::class));

        $Basket->setHash('new-hash');
        $Basket->addFrontendMessage('Basket warning');

        self::assertSame('new-hash', $Basket->getHash());
        self::assertSame('Basket warning', $Basket->getFrontendMessages()->toArray()[0]['message']);

        $Basket->clearFrontendMessages();
        self::assertTrue($Basket->getFrontendMessages()->isEmpty());
    }

    public function testSaveWithoutUserStopsAndEmptyBasketIsSerialized(): void
    {
        $FrontendView = $this->createMock(ProductListFrontendView::class);
        $FrontendView->method('toArray')->willReturn([
            'attributes' => ['ignored' => true],
            'products' => [],
            'sum' => '10.00'
        ]);
        $List = $this->createMock(ProductList::class);
        $List->method('getProducts')->willReturn([]);
        $List->method('getFrontendView')->willReturn($FrontendView);
        $List->method('toArray')->willReturn(['sum' => 10.0]);
        $Basket = $this->createBasket($List);
        $this->setProperty($Basket, 'id', 5);
        $this->setProperty($Basket, 'User', null);

        $Basket->save();
        $data = $Basket->toArray();

        self::assertSame(5, $data['id']);
        self::assertSame([], $data['products']);
        self::assertSame(['sum' => '10.00'], $data['calculations']);
        self::assertSame(['sum' => 10.0], $data['unformatted']);
    }

    public function testToOrderTransfersCurrencyAddressesAndPriceFactors(): void
    {
        $Currency = $this->createMock(Currency::class);
        $FactorList = $this->createMock(FactorList::class);
        $PriceFactors = $this->createMock(PriceFactors::class);
        $PriceFactors->method('toErpPriceFactorList')->willReturn($FactorList);
        $List = $this->createMock(ProductList::class);
        $List->method('calc')->willReturnSelf();
        $List->method('getProducts')->willReturn([]);
        $List->method('getCurrency')->willReturn($Currency);
        $List->method('getPriceFactors')->willReturn($PriceFactors);

        $InvoiceAddress = $this->createMock(Address::class);
        $DeliveryAddress = $this->createMock(Address::class);
        $Articles = $this->createMock(ArticleList::class);
        $Articles->expects(self::once())->method('importPriceFactors')->with($FactorList);
        $Articles->expects(self::once())->method('calc');

        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getInvoiceAddress')->willReturn($InvoiceAddress);
        $Order->method('getDeliveryAddress')->willReturn($DeliveryAddress);
        $Order->method('getArticles')->willReturn($Articles);
        $Order->expects(self::once())->method('clear');
        $Order->expects(self::once())->method('setCurrency')->with($Currency);
        $Order->expects(self::once())->method('setInvoiceAddress')->with($InvoiceAddress);
        $Order->expects(self::once())->method('setDeliveryAddress')->with($DeliveryAddress);
        $Order->expects(self::exactly(2))->method('update');

        $this->createBasket($List)->toOrder($Order);
    }

    private function createBasket(ProductList $List): Basket
    {
        $Basket = (new ReflectionClass(Basket::class))->newInstanceWithoutConstructor();
        $this->setProperty($Basket, 'List', $List);
        $this->setProperty($Basket, 'FrontendMessages', new Comments());
        $this->setProperty($Basket, 'id', false);
        $this->setProperty($Basket, 'hash', null);
        $this->setProperty($Basket, 'User', null);

        return $Basket;
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $Property = new ReflectionProperty($object, $name);
        $Property->setValue($object, $value);
    }
}
