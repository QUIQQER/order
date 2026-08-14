<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\Basket\BasketGuest;
use QUI\ERP\Order\Basket\Product;
use QUI\ERP\Products\Field\UniqueField;
use QUI\ERP\Products\Product\ProductList;
use QUI\ERP\Products\Product\ProductListFrontendView;
use ReflectionProperty;

class BasketGuestUnitTest extends TestCase
{
    public function testEmptyGuestBasketLifecycleAndCompatibilityMethods(): void
    {
        $Basket = new BasketGuest();

        self::assertSame(0, $Basket->count());
        self::assertSame($Basket->getProducts(), $Basket->getProducts());
        self::assertFalse($Basket->hasOrder());
        $Basket->save();
        $Basket->updateOrder();
        $Basket->clear();
        $Basket->import(null);

        $data = $Basket->toArray();
        self::assertSame([], $data['products']);
        self::assertArrayHasKey('calculations', $data);
        self::assertArrayHasKey('unformatted', $data);

        $this->expectException(\QUI\ERP\Order\Basket\Exception::class);
        $Basket->getOrder();
    }

    public function testAddProductAndSerializationKeepOnlyPublicCustomFields(): void
    {
        $PublicCustom = $this->createMock(UniqueField::class);
        $PublicCustom->method('isPublic')->willReturn(true);
        $PublicCustom->method('isCustomField')->willReturn(true);
        $PublicCustom->method('getId')->willReturn(7);
        $PublicCustom->method('getValue')->willReturn('engraving');
        $Private = $this->createMock(UniqueField::class);
        $Private->method('isPublic')->willReturn(false);
        $NonCustom = $this->createMock(UniqueField::class);
        $NonCustom->method('isPublic')->willReturn(true);
        $NonCustom->method('isCustomField')->willReturn(false);
        $Product = $this->createMock(Product::class);
        $Product->method('getId')->willReturn(42);
        $Product->method('getQuantity')->willReturn(3.5);
        $Product->method('getFields')->willReturn([$PublicCustom, $Private, $NonCustom]);
        $FrontendView = $this->createMock(ProductListFrontendView::class);
        $FrontendView->method('toArray')->willReturn([
            'attributes' => ['ignored' => true],
            'products' => ['ignored'],
            'sum' => '12.34'
        ]);
        $List = $this->createMock(ProductList::class);
        $List->expects(self::once())->method('addProduct')->with($Product);
        $List->method('getProducts')->willReturn([$Product]);
        $List->method('getFrontendView')->willReturn($FrontendView);
        $List->method('toArray')->willReturn(['sum' => 12.34]);
        $Basket = new BasketGuest();
        (new ReflectionProperty($Basket, 'List'))->setValue($Basket, $List);

        $Basket->addProduct($Product);
        $data = $Basket->toArray();

        self::assertSame([
            'id' => 42,
            'quantity' => 3.5,
            'fields' => [7 => 'engraving']
        ], $data['products'][0]);
        self::assertSame(['sum' => '12.34'], $data['calculations']);
        self::assertSame(['sum' => 12.34], $data['unformatted']);
    }

    public function testImportSkipsEntriesWithoutProductId(): void
    {
        $List = $this->createMock(ProductList::class);
        $List->expects(self::once())->method('clear');
        $List->expects(self::once())->method('recalculation');
        $List->expects(self::never())->method('addProduct');
        $Basket = new BasketGuest();
        (new ReflectionProperty($Basket, 'List'))->setValue($Basket, $List);

        $Basket->import([['quantity' => 2]]);
        self::assertTrue(true);
    }
}
