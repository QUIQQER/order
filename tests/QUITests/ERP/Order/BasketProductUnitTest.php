<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\Basket\Product as BasketProduct;
use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Handler\Fields;
use QUI\ERP\Products\Product\Types\Product as CatalogProduct;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class BasketProductUnitTest extends TestCase
{
    /** @var array<mixed> */
    private array $originalProducts;

    protected function setUp(): void
    {
        parent::setUp();
        $Property = new ReflectionProperty(Products::class, 'list');
        $this->originalProducts = $Property->getValue();
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Products::class, 'list'))->setValue(null, $this->originalProducts);
        parent::tearDown();
    }

    public function testConstructorCopiesCatalogLimitsAndLoadsCategoriesLazily(): void
    {
        $Category = $this->createMock(\QUI\ERP\Products\Interfaces\CategoryInterface::class);
        $Catalog = $this->createMock(CatalogProduct::class);
        $Catalog->method('getMaximumQuantity')->willReturn(8.0);
        $Catalog->method('getFields')->willReturn([]);
        $Catalog->method('getCategories')->willReturn([$Category]);
        $Catalog->method('getCategory')->willReturn($Category);
        (new ReflectionProperty(Products::class, 'list'))->setValue(null, [701001 => $Catalog]);

        $Product = new BasketProduct(701001, [
            'quantity' => 2,
            'fields' => $this->getCalculationFields(),
            'customFields' => ['invalid' => 'ignored']
        ]);

        self::assertSame(701001, $Product->getId());
        self::assertSame(2.0, $Product->getQuantity());
        self::assertSame(8.0, $Product->getMaximumQuantity());
        self::assertSame([$Category], $Product->getCategories());
        self::assertSame($Category, $Product->getCategory());
        self::assertSame([$Category], $Product->getCategories());
        self::assertSame($Category, $Product->getCategory());
    }

    public function testInvalidFieldPayloadsAreIgnored(): void
    {
        $Catalog = $this->createMock(CatalogProduct::class);
        $Catalog->method('getMaximumQuantity')->willReturn(false);
        $Catalog->method('getFields')->willReturn([]);
        (new ReflectionProperty(Products::class, 'list'))->setValue(null, [701002 => $Catalog]);
        $Product = new BasketProduct(701002, ['fields' => $this->getCalculationFields()]);
        $Method = new ReflectionMethod(BasketProduct::class, 'importFieldData');

        self::assertNull($Method->invoke($Product, 'invalid', ['value' => 'x']));
        self::assertNull($Method->invoke($Product, 'invalid', new \stdClass()));
    }

    public function testMissingCatalogCategoriesRemainEmpty(): void
    {
        $Product = (new ReflectionClass(BasketProduct::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($Product, 'id'))->setValue($Product, 999999);
        (new ReflectionProperty($Product, 'categories'))->setValue($Product, []);
        (new ReflectionProperty($Product, 'Category'))->setValue($Product, null);

        self::assertSame([], $Product->getCategories());
        self::assertNull($Product->getCategory());
    }

    public function testDescriptionCustomFieldsAndLegacyFieldPayloadsAreImported(): void
    {
        $Catalog = $this->createMock(CatalogProduct::class);
        $Catalog->method('getMaximumQuantity')->willReturn(12.0);
        $Catalog->method('getFields')->willReturn([]);
        (new ReflectionProperty(Products::class, 'list'))->setValue(null, [701003 => $Catalog]);

        $Product = new BasketProduct(701003, [
            'description' => 'Basket-specific description',
            'fields' => $this->getCalculationFields(),
            'customFields' => [
                1 => [
                    'id' => 1,
                    'identifier' => 1,
                    'type' => 'Price',
                    'value' => 15.0,
                    '__class__' => \QUI\ERP\Products\Field\Types\Price::class
                ]
            ]
        ]);
        $Method = new ReflectionMethod(BasketProduct::class, 'importFieldData');

        self::assertNotNull($Method->invoke($Product, 1, [
            'id' => 1,
            'value' => 20,
            'userinput' => 'manual price'
        ]));

        $ExistingField = Fields::getField(1);
        $ExistingField->setValue(25);
        self::assertNotNull($Method->invoke($Product, 1, $ExistingField));
        self::assertSame(12.0, $Product->getMaximumQuantity());
    }

    /** @return array<int, array<string, mixed>> */
    private function getCalculationFields(): array
    {
        return [
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
        ];
    }
}
