<?php

namespace QUITests\ERP\Order;

use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\Search;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class SearchUnitTest extends TestCase
{
    public function testFiltersNormalizeValuesAndIgnoreUnsupportedInput(): void
    {
        $Search = $this->createSearch();
        $Search->setFilter('status', null);
        $Search->setFilter('search', ['invalid']);
        $Search->setFilter('currency', ['invalid']);
        $Search->setFilter('unknown', 'ignored');
        $Search->setFilter('status', ['', '2']);
        $Search->setFilter('from', '1704067200');
        $Search->setFilter('to', '1704153600');
        $Search->setFilter('search', 'ORD-100');

        self::assertSame('ORD-100', $this->getProperty($Search, 'search'));
        self::assertSame('', $this->getProperty($Search, 'currency'));
        self::assertSame([
            ['filter' => 'status', 'value' => '2'],
            ['filter' => 'from', 'value' => date('Y-m-d 00:00:00', 1704067200)],
            ['filter' => 'to', 'value' => date('Y-m-d 23:59:59', 1704153600)]
        ], $this->getProperty($Search, 'filter'));

        $Search->clearFilter();
        self::assertSame([], $this->getProperty($Search, 'filter'));
        self::assertNull($this->getProperty($Search, 'search'));
    }

    public function testLimitAndOrderAcceptOnlyNormalizedAllowedValues(): void
    {
        $Search = $this->createSearch();
        $Search->limit('10', '25');
        $Search->order(' id ASC ');

        self::assertSame([10, 25], $this->getProperty($Search, 'limit'));
        self::assertSame('id ASC', $this->getProperty($Search, 'order'));

        $Search->order('id; DROP TABLE orders');
        self::assertSame('id ASC', $this->getProperty($Search, 'order'));
    }

    public function testEmptyQueryAppliesOrderAndLimit(): void
    {
        $Search = $this->createSearch();
        $Search->limit(5, 10);
        $Search->order('id asc');

        $Query = $this->getQuery($Search);

        self::assertStringContainsString('ORDER BY `id` asc', $Query->getSQL());
        self::assertSame(5, $Query->getFirstResult());
        self::assertSame(10, $Query->getMaxResults());
    }

    public function testFilteredQueryContainsDateStatusCurrencyAndSearchParameters(): void
    {
        $Search = $this->createSearch();
        $Search->setFilter('status', '3');
        $Search->setFilter('from', '2024-01-01 00:00:00');
        $Search->setFilter('to', '2024-01-31 23:59:59');
        $Search->setFilter('search', 'customer@example.test');

        $Query = $this->getQuery($Search);
        $sql = $Query->getSQL();
        $parameters = $Query->getParameters();

        self::assertStringContainsString('`status` = :filter0', $sql);
        self::assertStringContainsString('`c_date` >= :filter1', $sql);
        self::assertStringContainsString('`c_date` <= :filter2', $sql);
        self::assertStringContainsString('LIKE', $sql);
        self::assertSame(3, $parameters['filter0']);
        self::assertSame('2024-01-01 00:00:00', $parameters['filter1']);
        self::assertSame('2024-01-31 23:59:59', $parameters['filter2']);
        self::assertSame('%customer@example.test%', $parameters['search']);
    }

    private function createSearch(): Search
    {
        return (new ReflectionClass(Search::class))->newInstanceWithoutConstructor();
    }

    private function getQuery(Search $Search): QueryBuilder
    {
        return (new ReflectionMethod(Search::class, 'getQuery'))->invoke($Search);
    }

    private function getProperty(object $object, string $name): mixed
    {
        return (new ReflectionProperty($object, $name))->getValue($object);
    }
}
