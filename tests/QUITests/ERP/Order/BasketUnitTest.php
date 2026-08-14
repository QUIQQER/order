<?php

namespace QUITests\ERP\Order;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\ArticleInterface;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\PriceFactors\FactorList;
use QUI\ERP\Address;
use QUI\ERP\Comments;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Basket\Basket;
use QUI\ERP\Products\Product\ProductList;
use QUI\ERP\Products\Product\ProductListFrontendView;
use QUI\ERP\Products\Field\UniqueField;
use QUI\ERP\Products\Field\View;
use QUI\ERP\Products\Utils\PriceFactors;
use QUI\ERP\Order\Basket\Product as BasketProduct;
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
        $Article = $this->createMock(ArticleInterface::class);
        $Product = $this->createMock(BasketProduct::class);
        $Product->method('toArticle')->with(null, false)->willReturn($Article);
        $List->method('getProducts')->willReturn([new \stdClass(), $Product]);
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
        $Order->expects(self::once())->method('addArticle')->with($Article);
        $Order->expects(self::once())->method('setInvoiceAddress')->with($InvoiceAddress);
        $Order->expects(self::once())->method('setDeliveryAddress')->with($DeliveryAddress);
        $Order->expects(self::exactly(2))->method('update');

        $this->createBasket($List)->toOrder($Order);
    }

    public function testProductSerializationKeepsBasketIdentityAndProductMetadata(): void
    {
        $PrivateField = $this->createMock(UniqueField::class);
        $PrivateField->method('isPublic')->willReturn(false);
        $PrivateField->method('isCustomField')->willReturn(false);
        $CustomView = $this->createMock(View::class);
        $CustomView->method('getAttributes')->willReturn(['value' => 'engraving']);
        $CustomField = $this->createMock(UniqueField::class);
        $CustomField->method('isPublic')->willReturn(false);
        $CustomField->method('isCustomField')->willReturn(true);
        $CustomField->method('getId')->willReturn(19);
        $CustomField->method('getView')->willReturn($CustomView);
        $Product = $this->createMock(BasketProduct::class);
        $Product->method('getId')->willReturn(81);
        $Product->method('getUuid')->willReturn('basket-product-uuid');
        $Product->method('getProductSetParentUuid')->willReturn('parent-product-uuid');
        $Product->method('getQuantity')->willReturn(2.5);
        $Product->method('getFields')->willReturn([$PrivateField, $CustomField]);
        $FrontendView = $this->createMock(ProductListFrontendView::class);
        $FrontendView->method('toArray')->willReturn([
            'attributes' => [],
            'products' => [],
            'sum' => '20.00'
        ]);
        $List = $this->createMock(ProductList::class);
        $List->method('getProducts')->willReturn([$Product]);
        $List->method('getFrontendView')->willReturn($FrontendView);
        $List->method('toArray')->willReturn(['sum' => 20.0]);
        $Basket = $this->createBasket($List);
        $this->setProperty($Basket, 'id', 51);

        $data = $Basket->toArray();

        self::assertSame(51, $data['id']);
        self::assertSame(81, $data['products'][0]['id']);
        self::assertSame('basket-product-uuid', $data['products'][0]['uuid']);
        self::assertSame('parent-product-uuid', $data['products'][0]['productSetParentUuid']);
        self::assertSame(2.5, $data['products'][0]['quantity']);
        self::assertSame(['value' => 'engraving'], $data['products'][0]['fields'][19]);
    }

    public function testAddProductSynchronizesAnAttachedOrder(): void
    {
        $Article = $this->createMock(ArticleInterface::class);
        $Product = $this->createMock(BasketProduct::class);
        $Product->method('toArticle')->willReturn($Article);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->expects(self::once())->method('addArticle')->with($Article);
        $List = $this->createMock(ProductList::class);
        $List->expects(self::once())->method('addProduct')->with($Product);
        $Basket = $this->getMockBuilder(Basket::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['hasOrder', 'getOrder'])
            ->getMock();
        $this->setProperty($Basket, 'List', $List);
        $Basket->method('hasOrder')->willReturn(true);
        $Basket->method('getOrder')->willReturn($Order);

        $Basket->addProduct($Product);
    }

    public function testUpdateOrderUsesExistingOrderAndHandlesLookupFailures(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getUUID')->willReturn('updated-order');
        $Basket = $this->getMockBuilder(Basket::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder', 'toOrder'])
            ->getMock();
        $Basket->method('getOrder')->willReturn($Order);
        $Basket->expects(self::once())->method('toOrder')->with($Order);

        $Basket->updateOrder();
        self::assertSame('updated-order', $Basket->getHash());

        $Failure = $this->getMockBuilder(Basket::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder', 'toOrder'])
            ->getMock();
        $Failure->method('getOrder')->willThrowException(new QUI\Exception('lookup failed', 123));
        $Failure->expects(self::never())->method('toOrder');
        $Failure->updateOrder();
    }

    public function testSaveSerializesOnlySupportedProductsAndCustomFields(): void
    {
        $originalConnection = QUI::getDataBaseConnection();
        $Connection = $this->createMock(Connection::class);
        $Connection->expects(self::once())
            ->method('update')
            ->with(
                QUI::getDBTableName('baskets'),
                self::callback(static function (array $data): bool {
                    $products = json_decode($data['products'], true);

                    return $data['hash'] === 'save-basket'
                        && $products[0]['uuid'] === 'save-product'
                        && $products[0]['fields'][0]['value'] === 'custom';
                }),
                ['id' => 77, 'uid' => 'save-user']
            )
            ->willReturn(1);
        (new ReflectionProperty(QUI::class, 'QueryBuilder'))->setValue(null, $Connection);

        $CustomField = $this->createMock(UniqueField::class);
        $CustomField->expects(self::once())->method('setChangeableStatus')->with(false);
        $CustomField->method('isCustomField')->willReturn(true);
        $CustomField->method('getAttributes')->willReturn(['value' => 'custom']);
        $Product = $this->createMock(BasketProduct::class);
        $Product->method('getId')->willReturn(10);
        $Product->method('getUuid')->willReturn('save-product');
        $Product->method('getProductSetParentUuid')->willReturn('save-parent');
        $Product->method('getTitle')->willReturn('Saved product');
        $Product->method('getDescription')->willReturn('Description');
        $Product->method('getQuantity')->willReturn(3.0);
        $Product->method('getFields')->willReturn([$CustomField]);
        $List = $this->createMock(ProductList::class);
        $List->method('getProducts')->willReturn([new \stdClass(), $Product]);
        $User = $this->createMock(QUI\Interfaces\Users\User::class);
        $User->method('getUUID')->willReturn('save-user');
        $Basket = $this->createBasket($List);
        $this->setProperty($Basket, 'id', 77);
        $this->setProperty($Basket, 'hash', 'save-basket');
        $this->setProperty($Basket, 'User', $User);

        try {
            $Basket->save();
        } finally {
            (new ReflectionProperty(QUI::class, 'QueryBuilder'))->setValue(null, $originalConnection);
        }
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
