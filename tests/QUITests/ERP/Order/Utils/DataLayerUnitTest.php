<?php

namespace QUITests\ERP\Order\Utils;

use ArrayIterator;
use PHPUnit\Framework\TestCase;
use QUI\Exception as QUIException;
use QUI\Locale;
use QUI\ERP\Accounting\ArticleDiscount;
use QUI\ERP\Accounting\ArticleInterface;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Money\Price;
use QUI\ERP\Order\OrderInterface;
use QUI\ERP\Order\Utils\DataLayer;
use QUI\ERP\Products\Category\Category;
use QUI\ERP\Products\Field\Field;
use QUI\ERP\Products\Handler\Fields;
use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Product\Product;
use QUI\ERP\Products\Product\ProductList;
use QUI\ERP\Products\Product\Types\Product as ProductType;
use QUI\ERP\Shipping\Types\ShippingEntry;
use ReflectionClass;

class DataLayerUnitTest extends TestCase
{
    protected function setUp(): void
    {
        self::setProductCache([]);
    }

    protected function tearDown(): void
    {
        self::setProductCache([]);
    }

    public function testProductWithoutCategoryUsesEmptyCategoryName(): void
    {
        $ManufacturerField = $this->createMock(Field::class);
        $ManufacturerField->method('getValue')->willReturn([]);

        $ProductNumberField = $this->createMock(Field::class);
        $ProductNumberField->method('getValue')->willReturn('PHPUNIT-1');

        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('EUR');

        $Price = $this->createMock(Price::class);
        $Price->method('getPrice')->willReturn(10);
        $Price->method('getCurrency')->willReturn($Currency);

        $Product = $this->createMock(Product::class);
        $Product->method('getField')->willReturnMap([
            [Fields::FIELD_MANUFACTURER, $ManufacturerField],
            [Fields::FIELD_PRODUCT_NO, $ProductNumberField]
        ]);
        $Product->method('getTitle')->willReturn('PHPUnit product');
        $Product->method('getCategory')->willReturn(null);
        $Product->method('getCategories')->willReturn([]);
        $Product->method('getPrice')->willReturn($Price);

        $data = DataLayer::parseProduct($Product);

        self::assertSame('', $data['item_category']);
        self::assertSame('PHPUNIT-1', $data['item_id']);
        self::assertArrayNotHasKey('currency', $data);
    }

    public function testProductEventContainsCurrencyValueAndQuantity(): void
    {
        $data = DataLayer::parseProductEvent($this->createProduct('PRODUCT-EVENT'));

        self::assertSame('EUR', $data['currency']);
        self::assertSame(99.0, $data['value']);
        self::assertCount(1, $data['items']);
        self::assertSame('PRODUCT-EVENT', $data['items'][0]['item_id']);
        self::assertSame(1, $data['items'][0]['quantity']);
        self::assertArrayNotHasKey('currency', $data['items'][0]);
    }

    public function testProductListUsesCalculatedBasketPriceAndQuantity(): void
    {
        $productId = 910005;
        self::setProductCache([$productId => $this->createProduct('PRODUCT-LIST')]);

        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('EUR');

        $List = $this->createMock(ProductList::class);
        $List->method('getCurrency')->willReturn($Currency);
        $List->method('toArray')->willReturn([
            'sum' => 36.75,
            'products' => [[
                'id' => $productId,
                'quantity' => 3,
                'calculated_price' => 12.25
            ]]
        ]);

        $data = DataLayer::parseProductList($List);

        self::assertSame('EUR', $data['currency']);
        self::assertSame(36.75, $data['value']);
        self::assertCount(1, $data['items']);
        self::assertSame('PRODUCT-LIST', $data['items'][0]['item_id']);
        self::assertSame(12.25, $data['items'][0]['price']);
        self::assertSame(3, $data['items'][0]['quantity']);
    }

    public function testArticleUsesProductDataAndArticleAmounts(): void
    {
        $productId = 910001;
        self::setProductCache([$productId => $this->createProduct('PRODUCT-1')]);
        $Article = $this->createArticle($productId, 24.5, 2, 3.5);

        $data = DataLayer::parseArticle($Article);

        self::assertSame('PRODUCT-1', $data['item_id']);
        self::assertSame('PHPUnit product', $data['item_name']);
        self::assertSame(24.5, $data['price']);
        self::assertSame(2, $data['quantity']);
        self::assertSame(3.5, $data['discount']);
        self::assertArrayNotHasKey('currency', $data);
    }

    public function testArticlePassesLocaleToProductAndCategoryTitles(): void
    {
        $productId = 910003;
        $Locale = $this->createMock(Locale::class);
        $Category = $this->createMock(Category::class);
        $Category->expects(self::once())
            ->method('getTitle')
            ->with($Locale)
            ->willReturn('Localized category');

        self::setProductCache([
            $productId => $this->createProduct('PRODUCT-3', [$Category], $Locale)
        ]);

        $data = DataLayer::parseArticle(
            $this->createArticle($productId, 12.5, 1, null),
            $Locale
        );

        self::assertSame('Localized product', $data['item_name']);
        self::assertSame('Localized category', $data['item_category2']);
    }

    public function testTextArticleFallsBackToArticleData(): void
    {
        $productId = 910004;
        $Product = $this->createMock(ProductType::class);
        $Product->method('getField')->willThrowException(new QUIException('Missing product data'));
        self::setProductCache([$productId => $Product]);

        $Article = $this->createArticle($productId, 8.75, 4, null);
        $Article->method('getTitle')->willReturn('Manual article');

        $data = DataLayer::parseArticle($Article);

        self::assertSame('', $data['item_id']);
        self::assertSame('Manual article', $data['item_name']);
        self::assertSame('', $data['item_category']);
        self::assertSame('', $data['item_brand']);
        self::assertSame('', $data['item_variant']);
        self::assertSame(8.75, $data['price']);
        self::assertSame(4, $data['quantity']);
        self::assertArrayNotHasKey('currency', $data);
        self::assertArrayNotHasKey('discount', $data);
    }

    public function testOrderCombinesCalculationsCouponAndIndexedItems(): void
    {
        $originalPackageManager = \QUI::$PackageManager;
        $PackageManager = $this->createMock(\QUI\Package\Manager::class);
        $PackageManager->expects(self::once())
            ->method('isInstalled')
            ->with('quiqqer/coupons')
            ->willReturn(true);
        \QUI::$PackageManager = $PackageManager;
        $productId = 910002;
        self::setProductCache([$productId => $this->createProduct('PRODUCT-2')]);
        $Article = $this->createArticle($productId, 50.0, 2, null);
        $Articles = $this->createMock(ArticleList::class);
        $Articles->method('getCalculations')->willReturn([
            'sum' => 119.0,
            'vatArray' => [
                ['sum' => 12.0],
                ['sum' => 7.0]
            ]
        ]);
        $Articles->method('getIterator')->willReturn(new ArrayIterator([$Article]));

        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('EUR');

        $Payment = $this->createMock(Payment::class);
        $Payment->method('getTitle')->willReturn('Credit card');

        $Shipping = $this->createMock(ShippingEntry::class);
        $Shipping->method('getTitle')->willReturn('Standard shipping');
        $Shipping->method('getPrice')->willReturn(4.95);

        $Order = $this->createMock(OrderInterface::class);
        $Order->method('getArticles')->willReturn($Articles);
        $Order->method('getCurrency')->willReturn($Currency);
        $Order->method('getPayment')->willReturn($Payment);
        $Order->method('getShipping')->willReturn($Shipping);
        $Order->method('getDataEntry')->with('quiqqer-coupons')->willReturn('SUMMER');
        $Order->method('isSuccessful')->willReturn(1);
        $Order->method('getUUID')->willReturn('order-uuid');

        try {
            $data = DataLayer::parseOrder($Order);
        } finally {
            \QUI::$PackageManager = $originalPackageManager;
        }

        self::assertSame('EUR', $data['currency']);
        self::assertSame(119.0, $data['value']);
        self::assertSame(19.0, $data['tax']);
        self::assertSame('Credit card', $data['payment_type']);
        self::assertSame(4.95, $data['shipping']);
        self::assertSame('Standard shipping', $data['shipping_tier']);
        self::assertSame('SUMMER', $data['coupon']);
        self::assertSame('order-uuid', $data['transaction_id']);
        self::assertCount(1, $data['items']);
        self::assertSame(0, $data['items'][0]['index']);
        self::assertSame('PRODUCT-2', $data['items'][0]['item_id']);
        self::assertArrayNotHasKey('currency', $data['items'][0]);
    }

    public function testOrderWithoutOptionalSelectionsOmitsTheirData(): void
    {
        $originalPackageManager = \QUI::$PackageManager;
        $PackageManager = $this->createMock(\QUI\Package\Manager::class);
        $PackageManager->expects(self::once())
            ->method('isInstalled')
            ->with('quiqqer/coupons')
            ->willReturn(false);
        \QUI::$PackageManager = $PackageManager;

        $Articles = $this->createMock(ArticleList::class);
        $Articles->method('getCalculations')->willReturn([
            'sum' => 0.0,
            'vatArray' => []
        ]);
        $Articles->method('getIterator')->willReturn(new ArrayIterator([]));

        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('EUR');

        $Order = $this->createMock(OrderInterface::class);
        $Order->method('getArticles')->willReturn($Articles);
        $Order->method('getCurrency')->willReturn($Currency);
        $Order->method('getPayment')->willReturn(null);
        $Order->method('getShipping')->willReturn(null);
        $Order->method('isSuccessful')->willReturn(0);

        try {
            $data = DataLayer::parseOrder($Order);
        } finally {
            \QUI::$PackageManager = $originalPackageManager;
        }

        self::assertSame([], $data['items']);
        self::assertArrayNotHasKey('payment_type', $data);
        self::assertArrayNotHasKey('shipping', $data);
        self::assertArrayNotHasKey('shipping_tier', $data);
        self::assertArrayNotHasKey('coupon', $data);
        self::assertArrayNotHasKey('transaction_id', $data);
    }

    /**
     * @param array<int, Category> $categories
     */
    private function createProduct(
        string $productNumber,
        array $categories = [],
        ?Locale $Locale = null
    ): ProductType {
        $ManufacturerField = $this->createMock(Field::class);
        $ManufacturerField->method('getValue')->willReturn([]);

        $ProductNumberField = $this->createMock(Field::class);
        $ProductNumberField->method('getValue')->willReturn($productNumber);

        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('EUR');

        $Price = $this->createMock(Price::class);
        $Price->method('getPrice')->willReturn(99.0);
        $Price->method('getCurrency')->willReturn($Currency);

        $Product = $this->createMock(ProductType::class);
        $Product->method('getField')->willReturnMap([
            [Fields::FIELD_MANUFACTURER, $ManufacturerField],
            [Fields::FIELD_PRODUCT_NO, $ProductNumberField]
        ]);
        if ($Locale) {
            $Product->expects(self::once())
                ->method('getTitle')
                ->with($Locale)
                ->willReturn('Localized product');
        } else {
            $Product->method('getTitle')->willReturn('PHPUnit product');
        }

        $Product->method('getCategory')->willReturn(null);
        $Product->method('getCategories')->willReturn($categories);
        $Product->method('getPrice')->willReturn($Price);

        return $Product;
    }

    private function createArticle(
        int $productId,
        float $price,
        int $quantity,
        ?float $discount
    ): ArticleInterface {
        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('EUR');

        $Price = $this->createMock(Price::class);
        $Price->method('getValue')->willReturn($price);
        $Price->method('getCurrency')->willReturn($Currency);

        $Article = $this->createMock(ArticleInterface::class);
        $Article->method('getId')->willReturn($productId);
        $Article->method('getPrice')->willReturn($Price);
        $Article->method('getQuantity')->willReturn($quantity);

        if ($discount === null) {
            $Article->method('getDiscount')->willReturn(null);
        } else {
            $Discount = $this->createMock(ArticleDiscount::class);
            $Discount->method('getValue')->willReturn($discount);
            $Article->method('getDiscount')->willReturn($Discount);
        }

        return $Article;
    }

    /**
     * @param array<int, ProductType> $products
     */
    private static function setProductCache(array $products): void
    {
        $Reflection = new ReflectionClass(Products::class);
        $Reflection->getProperty('list')->setValue(null, $products);
    }
}
