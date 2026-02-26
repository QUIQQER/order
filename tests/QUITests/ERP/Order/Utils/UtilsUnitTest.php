<?php

namespace QUITests\ERP\Order\Utils;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\Utils\Utils;

class UtilsUnitTest extends TestCase
{
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
}
