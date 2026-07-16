<?php

namespace QUITests\ERP\Order\Utils;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Money\Price;
use QUI\ERP\Order\Utils\DataLayer;
use QUI\ERP\Products\Field\Field;
use QUI\ERP\Products\Handler\Fields;
use QUI\ERP\Products\Product\Product;

class DataLayerUnitTest extends TestCase
{
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

        self::assertSame('', $data['category']);
        self::assertSame('PHPUNIT-1', $data['item_id']);
    }
}
