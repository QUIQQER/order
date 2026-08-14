<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\PriceFactors\FactorList;
use QUI\ERP\Comments;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Basket\BasketOrder;
use QUI\ERP\Products\Product\ProductList;
use QUI\ERP\Products\Product\ProductListFrontendView;
use QUI\ERP\Products\Utils\PriceFactors;
use QUI\ERP\Order\Basket\Product as BasketProduct;
use ReflectionClass;
use ReflectionProperty;

class BasketOrderUnitTest extends TestCase
{
    public function testBasicStateAndOrderAccessors(): void
    {
        $List = $this->createMock(ProductList::class);
        $List->method('count')->willReturn(2);
        $Order = $this->createMock(AbstractOrder::class);
        $Basket = $this->createBasketOrder($List, $Order);
        $this->setProperty($Basket, 'id', 23);
        $Basket->setHash('updated-hash');

        self::assertSame(23, $Basket->getId());
        self::assertSame(2, $Basket->count());
        self::assertSame($List, $Basket->getProducts());
        self::assertSame('updated-hash', $Basket->getHash());
        self::assertTrue($Basket->hasOrder());
        self::assertSame($Order, $Basket->getOrder());
    }

    public function testClearDelegatesToProductAndOrderArticleLists(): void
    {
        $OrderArticles = $this->createMock(ArticleList::class);
        $OrderArticles->expects(self::once())->method('clear');
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getArticles')->willReturn($OrderArticles);
        $List = $this->createMock(ProductList::class);
        $List->expects(self::once())->method('clear');
        $Basket = $this->createBasketOrder($List, $Order);

        $Basket->clear();
    }

    public function testSaveAndToOrderDelegateToOrderUpdate(): void
    {
        $Basket = $this->getMockBuilder(BasketOrder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['updateOrder'])
            ->getMock();
        $Basket->expects(self::exactly(2))->method('updateOrder');

        $Basket->save();
        $Basket->toOrder();
    }

    public function testFrontendMessagesLifecycle(): void
    {
        $Basket = $this->createBasketOrder(
            $this->createMock(ProductList::class),
            $this->createMock(AbstractOrder::class)
        );

        $Basket->addFrontendMessage('Order basket warning');
        self::assertSame(
            'Order basket warning',
            $Basket->getFrontendMessages()->toArray()[0]['message']
        );

        $Basket->clearFrontendMessages();
        self::assertTrue($Basket->getFrontendMessages()->isEmpty());
    }

    public function testUpdateOrderTransfersCurrencyAndPriceFactors(): void
    {
        $Currency = $this->createMock(Currency::class);
        $FactorList = $this->createMock(FactorList::class);
        $PriceFactors = $this->createMock(PriceFactors::class);
        $PriceFactors->expects(self::once())->method('setCurrency')->with($Currency);
        $PriceFactors->method('toErpPriceFactorList')->willReturn($FactorList);
        $List = $this->createMock(ProductList::class);
        $List->method('getProducts')->willReturn([]);
        $List->method('getPriceFactors')->willReturn($PriceFactors);

        $Articles = $this->createMock(ArticleList::class);
        $Articles->expects(self::once())->method('clear');
        $Articles->expects(self::once())->method('setCurrency')->with($Currency);
        $Articles->expects(self::once())->method('importPriceFactors')->with($FactorList);
        $Articles->expects(self::once())->method('recalculate');

        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getArticles')->willReturn($Articles);
        $Order->method('getCurrency')->willReturn($Currency);
        $Order->expects(self::once())->method('update');

        $this->createBasketOrder($List, $Order)->updateOrder();
    }

    public function testEmptyOrderBasketIsSerializedWithCalculationsAndPriceFactors(): void
    {
        $FrontendView = $this->createMock(ProductListFrontendView::class);
        $FrontendView->method('toArray')->willReturn([
            'attributes' => [],
            'products' => [],
            'sum' => '25.00'
        ]);
        $PriceFactors = $this->createMock(PriceFactors::class);
        $PriceFactors->method('toArray')->willReturn([
            'beginning' => [],
            'middle' => [],
            'end' => []
        ]);
        $List = $this->createMock(ProductList::class);
        $List->method('getProducts')->willReturn([]);
        $List->method('getFrontendView')->willReturn($FrontendView);
        $List->method('getPriceFactors')->willReturn($PriceFactors);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getUUID')->willReturn('order-uuid');
        $Basket = $this->createBasketOrder($List, $Order);
        $this->setProperty($Basket, 'id', 42);

        $data = $Basket->toArray();

        self::assertSame(42, $data['id']);
        self::assertSame('order-uuid', $data['orderHash']);
        self::assertSame([], $data['products']);
        self::assertSame(
            ['beginning' => [], 'middle' => [], 'end' => []],
            $data['priceFactors']
        );
        self::assertSame(['sum' => '25.00'], $data['calculations']);
    }

    public function testProductSerializationIncludesQuantityAndOrderIdentity(): void
    {
        $Product = $this->createMock(BasketProduct::class);
        $Product->method('getId')->willReturn(91);
        $Product->method('getQuantity')->willReturn(4.0);
        $Product->method('getFields')->willReturn([]);
        $FrontendView = $this->createMock(ProductListFrontendView::class);
        $FrontendView->method('toArray')->willReturn([
            'attributes' => [],
            'products' => [],
            'sum' => '40.00'
        ]);
        $PriceFactors = $this->createMock(PriceFactors::class);
        $PriceFactors->method('toArray')->willReturn([]);
        $List = $this->createMock(ProductList::class);
        $List->method('getProducts')->willReturn([$Product]);
        $List->method('getFrontendView')->willReturn($FrontendView);
        $List->method('getPriceFactors')->willReturn($PriceFactors);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getUUID')->willReturn('serialized-order');
        $Basket = $this->createBasketOrder($List, $Order);

        $data = $Basket->toArray();

        self::assertSame('serialized-order', $data['orderHash']);
        self::assertSame(91, $data['products'][0]['id']);
        self::assertSame(4.0, $data['products'][0]['quantity']);
    }

    public function testAddProductAndRemovePositionSynchronizeOrder(): void
    {
        $Product = $this->createMock(BasketProduct::class);
        $List = $this->createMock(ProductList::class);
        $List->method('getProducts')->willReturn([]);
        $List->expects(self::once())->method('addProduct')->with($Product);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->expects(self::once())->method('removeArticle')->with(2);
        $Order->expects(self::once())->method('update');
        $Basket = $this->getMockBuilder(BasketOrder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['updateOrder', 'readOrder'])
            ->getMock();
        $this->setProperty($Basket, 'List', $List);
        $this->setProperty($Basket, 'Order', $Order);
        $this->setProperty($Basket, 'hash', 'order-hash');
        $Basket->expects(self::once())->method('updateOrder');
        $Basket->expects(self::once())->method('readOrder');

        $Basket->addProduct($Product);
        $Basket->removePosition(2);
    }

    private function createBasketOrder(ProductList $List, AbstractOrder $Order): BasketOrder
    {
        $Basket = (new ReflectionClass(BasketOrder::class))->newInstanceWithoutConstructor();
        $this->setProperty($Basket, 'List', $List);
        $this->setProperty($Basket, 'Order', $Order);
        $this->setProperty($Basket, 'FrontendMessages', new Comments());
        $this->setProperty($Basket, 'hash', 'order-hash');
        $this->setProperty($Basket, 'id', null);

        return $Basket;
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $Property = new ReflectionProperty($object, $name);
        $Property->setValue($object, $value);
    }
}
