<?php

namespace QUITests\ERP\Order\Utils;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Controls\AbstractOrderingStep;
use QUI\ERP\Order\OrderInterface;
use QUI\ERP\Order\OrderView;
use QUI\ERP\Order\Utils\Utils;
use QUI\ERP\Products\Field\Types\BasketConditions;
use QUI\ERP\Products\Field\Field;
use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Product\ProductList;
use QUI\ERP\Products\Product\TextProduct;
use QUI\ERP\Products\Product\Types\Product as ProductType;
use QUI\Interfaces\Projects\Site;
use QUI\Projects\Project;
use ReflectionClass;

class UtilsUnitTest extends TestCase
{
    protected function setUp(): void
    {
        self::setCachedOrderProcessUrl(null);
        self::setProductCache([]);
    }

    protected function tearDown(): void
    {
        self::setCachedOrderProcessUrl(null);
        self::setProductCache([]);
    }

    public function testOrderProcessCheckoutAndShoppingCartResolveConfiguredSites(): void
    {
        $OrderProcess = $this->createMock(Site::class);
        $ShoppingCart = $this->createMock(Site::class);
        $Project = $this->createMock(Project::class);
        $Project->method('getSites')->willReturnCallback(
            static function (array $query) use ($OrderProcess, $ShoppingCart): array {
                return match ($query['where']['type']) {
                    'quiqqer/order:types/orderingProcess' => [$OrderProcess],
                    'quiqqer/order:types/shoppingCart' => [$ShoppingCart],
                    default => []
                };
            }
        );

        self::assertSame($OrderProcess, Utils::getOrderProcess($Project));
        self::assertSame($OrderProcess, Utils::getCheckout($Project));
        self::assertSame($ShoppingCart, Utils::getShoppingCart($Project));
    }

    public function testMissingOrderAndShoppingCartSitesThrowOrderException(): void
    {
        $Project = $this->createMock(Project::class);
        $Project->method('getSites')->willReturn([]);

        try {
            Utils::getOrderProcess($Project);
            self::fail('Missing order process must throw an exception.');
        } catch (\QUI\ERP\Order\Exception) {
            self::assertTrue(true);
        }

        $this->expectException(\QUI\ERP\Order\Exception::class);
        Utils::getShoppingCart($Project);
    }

    public function testOrderProcessUrlsUseCacheStepAndHash(): void
    {
        $Site = $this->createMock(Site::class);
        $Site->expects(self::once())->method('getUrlRewritten')->willReturn('/checkout');

        $Project = $this->createMock(Project::class);
        $Project->expects(self::once())->method('getSites')->willReturn([$Site]);

        $Step = $this->createMock(AbstractOrderingStep::class);
        $Step->method('getName')->willReturn('CustomerData');

        self::assertSame('/checkout', Utils::getOrderProcessUrl($Project));
        self::assertSame('/checkout/CustomerData', Utils::getOrderProcessUrl($Project, $Step));
        self::assertSame('/checkout/Order/hash-123', Utils::getOrderProcessUrlForHash($Project, 'hash-123'));
    }

    public function testOrderUrlRejectsUnsupportedOrderAndBuildsViewUrl(): void
    {
        $Project = $this->createMock(Project::class);
        $Project->expects(self::once())->method('getSites')->willReturn([$this->createUrlSite('/checkout')]);

        self::assertSame('', Utils::getOrderUrl($Project, $this->createMock(OrderInterface::class)));
        self::assertSame('/checkout/Order/order-uuid', Utils::getOrderUrl($Project, $this->createOrderView()));
    }

    public function testOrderProfileUrlPreservesHtmlEnding(): void
    {
        $Project = $this->createMock(Project::class);
        $Project->expects(self::once())->method('getSites')->with([
            'where' => ['type' => 'quiqqer/frontend-users:types/profile'],
            'limit' => 1
        ])->willReturn([$this->createUrlSite('/profile.html')]);

        self::assertSame(
            '/profile/erp/erp-order#order-uuid.html',
            Utils::getOrderProfileUrl($Project, $this->createOrderView())
        );
    }

    public function testOrderProfileUrlReturnsEmptyWithoutProfileSite(): void
    {
        $Project = $this->createMock(Project::class);
        $Project->method('getSites')->willReturn([]);

        self::assertSame('', Utils::getOrderProfileUrl($Project, $this->createOrderView()));
    }

    public function testOrderProfileUrlRejectsUnsupportedOrderType(): void
    {
        $Project = $this->createMock(Project::class);
        $Project->expects(self::never())->method('getSites');

        self::assertSame(
            '',
            Utils::getOrderProfileUrl($Project, $this->createMock(OrderInterface::class))
        );
    }

    public function testOrderProfileUrlReturnsEmptyWhenSiteUrlFails(): void
    {
        $Site = $this->createMock(Site::class);
        $Site->method('getUrlRewritten')->willThrowException(new \QUI\Exception('URL unavailable'));
        $Project = $this->createMock(Project::class);
        $Project->method('getSites')->willReturn([$Site]);

        self::assertSame('', Utils::getOrderProfileUrl($Project, $this->createOrderView()));
    }

    public function testMissingPaymentIsAlwaysChangeable(): void
    {
        self::assertTrue(Utils::isPaymentChangeable(null));
    }

    public function testBasketProductEditabilityUsesBasketCondition(): void
    {
        $Editable = $this->createMock(ProductType::class);
        $Editable->method('getFieldsByType')->with('BasketConditions')->willReturn([]);

        $RestrictedCondition = $this->createMock(BasketConditions::class);
        $RestrictedCondition->method('getValue')->willReturn(BasketConditions::TYPE_3);
        $Restricted = $this->createMock(ProductType::class);
        $Restricted->method('getFieldsByType')
            ->with('BasketConditions')
            ->willReturn([$RestrictedCondition]);

        $Broken = $this->createMock(ProductType::class);
        $Broken->method('getFieldsByType')->willThrowException(new \QUI\Exception('Broken product'));

        self::setProductCache([
            900001 => $Editable,
            900002 => $Restricted,
            900003 => $Broken
        ]);

        self::assertTrue(Utils::isBasketProductEditable(['id' => 900001]));
        self::assertFalse(Utils::isBasketProductEditable(['id' => 900002]));
        self::assertFalse(Utils::isBasketProductEditable(['id' => 900003]));
    }

    public function testTextArticlesAreImportedAndEntriesWithoutIdAreIgnored(): void
    {
        $List = $this->createMock(ProductList::class);
        $List->expects(self::once())
            ->method('addProduct')
            ->with(self::callback(
                static fn(object $Product): bool => $Product instanceof TextProduct
                    && $Product->getTitle() === 'Manual item'
            ));

        $result = Utils::importProductsToBasketList($List, [
            ['title' => 'Entry without ID'],
            [
                'id' => -1,
                'title' => 'Manual item',
                'price_currency' => 'EUR'
            ]
        ]);

        self::assertSame($List, $result);
    }

    public function testUnavailableAndUnknownProductImportsAreSkippedWithFrontendMessage(): void
    {
        $ProductNumber = $this->createMock(Field::class);
        $ProductNumber->method('getValue')->willReturn('SKU-404');
        $Unavailable = $this->createMock(ProductType::class);
        $Unavailable->method('isActive')->willReturn(false);
        $Unavailable->method('getTitle')->willReturn('Unavailable product');
        $Unavailable->method('getField')->willReturn($ProductNumber);
        self::setProductCache([900004 => $Unavailable]);
        $List = $this->createMock(ProductList::class);
        $List->expects(self::never())->method('addProduct');
        $Order = $this->createMock(AbstractOrder::class);
        $Order->expects(self::once())
            ->method('addFrontendMessage')
            ->with(self::isType('string'));

        self::assertSame($List, Utils::importProductsToBasketList($List, [
            [
                'id' => 900004,
                'price_currency' => 'EUR'
            ],
            [
                'id' => 900005,
                'class' => \stdClass::class,
                'price_currency' => 'EUR'
            ]
        ], $Order));
    }

    public function testActiveCatalogProductIsRebuiltAndAddedToBasketList(): void
    {
        $Catalog = $this->createMock(ProductType::class);
        $Catalog->method('isActive')->willReturn(true);
        $Catalog->method('getMaximumQuantity')->willReturn(9.0);
        $Catalog->method('getFields')->willReturn([]);
        self::setProductCache([900006 => $Catalog]);
        $List = $this->createMock(ProductList::class);
        $List->expects(self::once())
            ->method('addProduct')
            ->with(self::callback(
                static fn(object $Product): bool => $Product instanceof \QUI\ERP\Order\Basket\Product
                    && $Product->getId() === 900006
                    && $Product->getQuantity() === 2.0
            ));

        $result = Utils::importProductsToBasketList($List, [[
            'id' => 900006,
            'quantity' => 2,
            'price_currency' => 'EUR',
            'fields' => [
                1 => [
                    'id' => 1,
                    'identifier' => 1,
                    'type' => 'Price',
                    'value' => 10.0,
                    '__class__' => \QUI\ERP\Products\Field\Types\Price::class
                ],
                2 => [
                    'id' => 2,
                    'identifier' => 2,
                    'type' => 'Vat',
                    'value' => 19,
                    '__class__' => \QUI\ERP\Products\Field\Types\Vat::class
                ]
            ]
        ]]);

        self::assertSame($List, $result);
    }

    public function testGetCompareProductArrayFiltersAndOrdersKnownFields(): void
    {
        $product = [
            'unknown' => 'ignored',
            'display_unitPrice' => 12.34,
            'title' => 'Product A',
            'id' => 42,
            'customFields' => ['a' => 'b'],
            'quantity' => 7,
            'class' => 'MyClass',
            'unitPrice' => 9.99
        ];

        $result = Utils::getCompareProductArray($product);

        $this->assertSame(
            [
                'id' => 42,
                'title' => 'Product A',
                'unitPrice' => 9.99,
                'class' => 'MyClass',
                'customFields' => ['a' => 'b'],
                'display_unitPrice' => 12.34
            ],
            $result
        );
    }

    public function testGetCompareProductArrayReturnsEmptyArrayForNoKnownFields(): void
    {
        $this->assertSame([], Utils::getCompareProductArray([
            'foo' => 'bar',
            'quantity' => 1
        ]));
    }

    public function testGetCompareProductArrayContainsAllKnownNeedlesWhenPresent(): void
    {
        $product = [
            'id' => 1,
            'title' => 'Title',
            'articleNo' => 'A-1',
            'description' => 'Desc',
            'unitPrice' => 9.9,
            'displayPrice' => 10.9,
            'class' => 'SomeClass',
            'customFields' => ['x' => 1],
            'customData' => ['y' => 2],
            'display_unitPrice' => 11.9
        ];

        $this->assertSame($product, Utils::getCompareProductArray($product));
    }

    public function testGetMergedProductListMergesEqualProductsAndSumsQuantity(): void
    {
        $products = [
            [
                'id' => 100,
                'title' => 'A',
                'quantity' => 2,
                'unitPrice' => 10,
                'extra' => 'x'
            ],
            [
                'id' => 100,
                'title' => 'A',
                'quantity' => 3,
                'unitPrice' => 10,
                'extra' => 'y'
            ],
            [
                'id' => 101,
                'title' => 'B',
                'quantity' => 1,
                'unitPrice' => 20
            ]
        ];

        $result = Utils::getMergedProductList($products);

        $this->assertCount(2, $result);
        $this->assertSame(5, $result[0]['quantity']);
        $this->assertSame(100, $result[0]['id']);
        $this->assertSame(101, $result[1]['id']);
        $this->assertSame(1, $result[1]['quantity']);
    }

    public function testGetMergedProductListDoesNotMergeWhenCompareFieldsDiffer(): void
    {
        $products = [
            [
                'id' => 200,
                'title' => 'A',
                'quantity' => 1,
                'unitPrice' => 10
            ],
            [
                'id' => 200,
                'title' => 'A',
                'quantity' => 2,
                'unitPrice' => 11
            ]
        ];

        $result = Utils::getMergedProductList($products);

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['quantity']);
        $this->assertSame(2, $result[1]['quantity']);
    }

    public function testGetMergedProductListKeepsOrderAndMergesMultipleMatches(): void
    {
        $products = [
            [
                'id' => 300,
                'title' => 'A',
                'quantity' => 1,
                'unitPrice' => 10
            ],
            [
                'id' => 301,
                'title' => 'B',
                'quantity' => 2,
                'unitPrice' => 20
            ],
            [
                'id' => 300,
                'title' => 'A',
                'quantity' => 3,
                'unitPrice' => 10
            ],
            [
                'id' => 300,
                'title' => 'A',
                'quantity' => 4,
                'unitPrice' => 10
            ]
        ];

        $result = Utils::getMergedProductList($products);

        $this->assertCount(2, $result);
        $this->assertSame(300, $result[0]['id']);
        $this->assertSame(8, $result[0]['quantity']);
        $this->assertSame(301, $result[1]['id']);
        $this->assertSame(2, $result[1]['quantity']);
    }

    public function testGetMergedProductListReturnsEmptyArrayForEmptyInput(): void
    {
        $this->assertSame([], Utils::getMergedProductList([]));
    }

    private function createUrlSite(string $url): Site
    {
        $Site = $this->createMock(Site::class);
        $Site->method('getUrlRewritten')->willReturn($url);

        return $Site;
    }

    private function createOrderView(): OrderView
    {
        $Articles = $this->createMock(ArticleList::class);
        $Currency = $this->createMock(Currency::class);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getArticles')->willReturn($Articles);
        $Order->method('getCurrency')->willReturn($Currency);
        $Order->method('getAttributes')->willReturn([]);
        $Order->method('getUUID')->willReturn('order-uuid');

        return new OrderView($Order);
    }

    private static function setCachedOrderProcessUrl(?string $url): void
    {
        $Reflection = new ReflectionClass(Utils::class);
        $Reflection->getProperty('url')->setValue(null, $url);
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
