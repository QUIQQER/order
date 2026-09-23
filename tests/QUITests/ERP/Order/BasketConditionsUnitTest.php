<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\Basket\BasketGuest;
use QUI\ERP\Order\Utils\Utils;
use QUI\ERP\Products\Field\Types\BasketConditions;
use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Product\ProductList;
use QUI\ERP\Products\Product\Types\Product;
use ReflectionProperty;

class BasketConditionsUnitTest extends TestCase
{
    private array $productCache;

    protected function setUp(): void
    {
        $Cache = new ReflectionProperty(Products::class, 'list');
        $this->productCache = $Cache->getValue();
        $products = [];

        foreach ([900001 => 1, 900002 => 2, 900006 => 6, 900007 => 6, 900008 => 1] as $id => $condition) {
            $Condition = new BasketConditions(900009);
            $Condition->setValue($condition);
            $Price = new \QUI\ERP\Products\Field\Types\Price(1);
            $Price->setValue(10.0);
            $Vat = new \QUI\ERP\Products\Field\Types\Vat(2);
            $Vat->setValue(-1);
            $ArticleNo = new \QUI\ERP\Products\Field\Types\Input(3);
            $ArticleNo->setValue('TEST-' . $id);
            $Product = $this->createMock(Product::class);
            $Product->method('isActive')->willReturn($id !== 900008);
            $Product->method('getMaximumQuantity')->willReturn(99.0);
            $Product->method('getFields')->willReturn([$Price, $Vat, $ArticleNo, $Condition]);
            $Product->method('getField')->willReturnMap([[1, $Price], [2, $Vat], [3, $ArticleNo], [900009, $Condition]]);
            $Product->method('getFieldsByType')->with('BasketConditions')->willReturn([$Condition]);
            $products[$id] = $Product;
        }

        $Cache->setValue(null, $products);
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Products::class, 'list'))->setValue(null, $this->productCache);
    }

    public static function basketScenarios(): iterable
    {
        yield 'single with quantity limited to one' => [[900002], [900002], [1.0]];
        yield 'single allowing multiple units' => [[900006], [900006], [4.0]];
        yield 'regular replaces single' => [[900002, 900001], [900001], [4.0]];
        yield 'regular replaces multiple-unit single' => [[900006, 900001], [900001], [4.0]];
        yield 'single replaces single' => [[900002, 900006], [900006], [4.0]];
        yield 'second multiple-unit single wins' => [[900006, 900007], [900007], [4.0]];
        yield 'single does not clear regular basket' => [[900001, 900002], [900001], [4.0]];
        yield 'multiple-unit single does not clear regular basket' => [[900001, 900006], [900001], [4.0]];
        yield 'unavailable product does not replace single' => [[900006, 900008], [900006], [4.0]];
    }

    #[DataProvider('basketScenarios')]
    public function testGuestAndAccountImportsRespectStandaloneProducts(
        array $ids,
        array $expectedIds,
        array $expectedQuantities
    ): void {
        foreach ([false, true] as $guest) {
            // Keep the actual reconstructed products; isolate pricing and database persistence.
            $stored = [];
            $List = $this->createMock(ProductList::class);
            $List->method('count')->willReturnCallback(static function () use (&$stored): int {
                return count($stored);
            });
            $List->method('clear')->willReturnCallback(static function () use (&$stored): void {
                $stored = [];
            });
            $List->method('addProduct')->willReturnCallback(static function ($Product) use (&$stored): void {
                $stored[] = $Product;
            });
            $data = array_map(self::productData(...), $ids);

            if ($guest) {
                $Basket = new BasketGuest();
                (new ReflectionProperty($Basket, 'List'))->setValue($Basket, $List);
                $Basket->import($data);
            } else {
                Utils::importProductsToBasketList(
                    $List,
                    $data,
                    $this->createMock(\QUI\ERP\Order\AbstractOrder::class)
                );
            }

            self::assertSame($expectedIds, array_map(static fn($Product) => $Product->getId(), $stored));
            self::assertSame(
                $expectedQuantities,
                array_map(static fn($Product) => $Product->getQuantity(), $stored)
            );
        }
    }

    public function testSubmittedFieldsCannotRemoveTheCatalogQuantityRestriction(): void
    {
        $data = self::productData(900002);
        $data['fields'][900009] = [
            'identifier' => 900009,
            'type' => 'BasketConditions',
            'value' => 1,
            '__class__' => BasketConditions::class
        ];
        $List = $this->createMock(ProductList::class);
        $List->expects(self::once())->method('addProduct')->with(self::callback(
            static fn($Product): bool => $Product->getQuantity() === 1.0
        ));
        Utils::importProductsToBasketList($List, [$data]);
    }

    public function testStandaloneProductSurvivesStoredBasketAndCheckoutReentry(): void
    {
        $Users = \QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $previousUser = $Session->getValue($Users);
        $User = $Users->getSystemUser();
        $Basket = null;
        $Order = null;
        $request = $_REQUEST;

        try {
            $Session->setValue($Users, $User);
            $_REQUEST['step'] = 'CustomerData';
            $Basket = \QUI\ERP\Order\Factory::getInstance()->createBasket($User);
            $Basket->import([self::productData(900006)]);
            self::assertSame(1, $Basket->count());
            $Order = \QUI\ERP\Order\Factory::getInstance()->createOrderInProcess();

            // Opening checkout without a hash imports the regular basket on each visit.
            for ($visit = 0; $visit < 2; $visit++) {
                $ReloadedBasket = new \QUI\ERP\Order\Basket\Basket($Basket->getId(), $User);
                self::assertSame(1, $ReloadedBasket->count());
                new \QUITests\ERP\Order\Fixtures\ConstructableOrderProcess(
                    $Order,
                    $ReloadedBasket,
                    ['CustomerData' => new \QUITests\ERP\Order\Fixtures\RenderableOrderStep(
                        'CustomerData',
                        \QUI\ERP\Order\Controls\OrderProcess\CustomerData::class
                    )],
                    new \QUITests\ERP\Order\Fixtures\RenderableOrderStep(
                        'Processing',
                        \QUI\ERP\Order\Controls\OrderProcess\Processing::class
                    )
                );
                self::assertSame(1, $Order->getArticles()->count());
                $articles = $Order->getArticles()->toArray()['articles'];
                self::assertSame(900006, (int)$articles[0]['id']);
                self::assertSame(4.0, (float)$articles[0]['quantity']);
            }
        } finally {
            $Handler = \QUI\ERP\Order\Handler::getInstance();
            $Connection = \QUI::getDataBaseConnection();

            if ($Basket) {
                $Connection->delete($Handler->tableBasket(), ['id' => $Basket->getId()]);
            }

            if ($Order) {
                $Connection->delete($Handler->tableOrderProcess(), ['hash' => $Order->getUUID()]);
            }

            $Session->setValue($Users, $previousUser);
            $_REQUEST = $request;
        }
    }

    private static function productData(int $id): array
    {
        return [
            'id' => $id,
            'quantity' => 4,
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
                    'value' => -1,
                    '__class__' => \QUI\ERP\Products\Field\Types\Vat::class
                ]
            ]
        ];
    }
}
