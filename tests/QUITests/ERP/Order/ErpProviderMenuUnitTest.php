<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\Controls\Sitemap\Item;
use QUI\Controls\Sitemap\Map;
use QUI\ERP\Order\ErpProvider;

class ErpProviderMenuUnitTest extends TestCase
{
    public function testAddMenuItemsCreatesAccountingAndOrderStructure(): void
    {
        $Map = new Map(['name' => 'root']);

        ErpProvider::addMenuItems($Map);
        $data = $Map->toArray();

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
        $this->assertSame('accounting', $data['items'][0]['name']);
        $this->assertCount(1, $data['items'][0]['items']);
        $this->assertSame('order', $data['items'][0]['items'][0]['name']);
        $this->assertCount(2, $data['items'][0]['items'][0]['items']);
        $this->assertSame('invoice-create', $data['items'][0]['items'][0]['items'][0]['name']);
        $this->assertSame('invoice-drafts', $data['items'][0]['items'][0]['items'][1]['name']);
    }

    public function testAddMenuItemsReusesExistingAccountingNode(): void
    {
        $Map = new Map(['name' => 'root']);
        $Map->appendChild(
            new Item([
                'name' => 'accounting'
            ])
        );

        ErpProvider::addMenuItems($Map);
        $data = $Map->toArray();

        $this->assertCount(1, $data['items']);
        $this->assertSame('accounting', $data['items'][0]['name']);
        $this->assertCount(1, $data['items'][0]['items']);
        $this->assertSame('order', $data['items'][0]['items'][0]['name']);
    }
}
