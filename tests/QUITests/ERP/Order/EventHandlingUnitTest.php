<?php

namespace QUITests\ERP\Order;

use DusanKasan\Knapsack\Collection;
use PHPUnit\Framework\TestCase;
use QUI\Control;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Controls\Buttons\ProductToBasket;
use QUI\ERP\Order\EventHandling;
use QUI\ERP\Order\ProcessingStatus\Status;
use QUI\ERP\Products\Product\Product;
use QUI\ERP\Products\Product\Types\AbstractType;
use QUI\Interfaces\Projects\Site;
use QUI\Projects\Project;
use QUI\Rewrite;
use QUI\Smarty\Collector;
use QUI\Template;
use ReflectionMethod;

class EventHandlingUnitTest extends TestCase
{
    public function testProductViewButtonIsAppendedAndDisabledForUnavailableProduct(): void
    {
        $Product = $this->createMock(Product::class);
        $ProductControl = new Control([
            'data-qui-option-available' => false
        ]);
        $Collection = Collection::from([]);

        EventHandling::onQuiqqerProductsProductViewButtons(
            $Product,
            $Collection,
            $ProductControl
        );

        $buttons = iterator_to_array($Collection);
        self::assertCount(1, $buttons);
        self::assertInstanceOf(ProductToBasket::class, $buttons[0]);
        self::assertTrue($buttons[0]->getAttribute('disabled'));
    }

    public function testSimpleCheckoutRequestSelectsOrderProcessSite(): void
    {
        $CheckoutSite = $this->createMock(Site::class);
        $CheckoutSite->method('getUrlRewritten')->willReturn('/checkout/');
        $Project = $this->createMock(Project::class);
        $Project->method('getSites')->willReturn([$CheckoutSite]);
        $Rewrite = $this->createMock(Rewrite::class);
        $Rewrite->method('getProject')->willReturn($Project);
        $Rewrite->expects(self::once())->method('setSite')->with($CheckoutSite);

        (new ReflectionMethod(EventHandling::class, 'handleRequest'))
            ->invoke(null, $Rewrite, 'checkout');
    }

    public function testRequestWithoutProjectStopsWithoutChangingSite(): void
    {
        $Rewrite = $this->createMock(Rewrite::class);
        $Rewrite->method('getProject')->willReturn(null);
        $Rewrite->expects(self::never())->method('setSite');

        EventHandling::onRequest($Rewrite, 'checkout');
        self::assertTrue(true);
    }

    public function testDetailEquipmentFallbackCreatesProductLink(): void
    {
        $Product = $this->createMock(AbstractType::class);
        $Product->method('getUrl')->willReturn('/products/example');
        $Collector = new Collector();

        EventHandling::onDetailEquipmentButtons($Collector, $Product);

        self::assertSame(
            '<a href="/products/example"><span class="fa fa-chevron-right"></span></a>',
            $Collector->getContent()
        );
    }

    public function testTemplateEventsAppendConfigurationAndFrontendScripts(): void
    {
        $Template = $this->createMock(Template::class);
        $Template->expects(self::once())
            ->method('extendHeader')
            ->with(self::stringContains('window.QUIQQER_ORDER_ORDER_PROCESS_MERGE'));
        $Template->expects(self::once())
            ->method('extendFooter')
            ->with(self::callback(
                static fn(string $html): bool => str_contains($html, 'checkoutBootstrap.js')
                    && str_contains($html, 'dataLayerTracking.js')
            ));

        EventHandling::onTemplateGetHeader($Template);
        EventHandling::onTemplateEnd(new Collector(), $Template);
    }

    public function testStatusNotificationStopsForManualSaveOrDisabledStatus(): void
    {
        $ManualOrder = $this->createMock(AbstractOrder::class);
        $ManualOrder->method('getAttribute')->with('userSave')->willReturn(true);
        $ManualStatus = $this->createMock(Status::class);
        $ManualStatus->expects(self::never())->method('isAutoNotification');

        EventHandling::onQuiqqerOrderProcessStatusChange($ManualOrder, $ManualStatus);

        $AutomaticOrder = $this->createMock(AbstractOrder::class);
        $AutomaticOrder->method('getAttribute')->with('userSave')->willReturn(false);
        $DisabledStatus = $this->createMock(Status::class);
        $DisabledStatus->expects(self::once())->method('isAutoNotification')->willReturn(false);

        EventHandling::onQuiqqerOrderProcessStatusChange($AutomaticOrder, $DisabledStatus);
        self::assertTrue(true);
    }

    public function testSalesOrderWithoutLinkedOrderStopsImmediately(): void
    {
        $SalesOrder = $this->createMock(\QUI\ERP\SalesOrders\SalesOrder::class);
        $SalesOrder->method('getData')->with('orderId')->willReturn(null);
        $SalesOrder->expects(self::never())->method('getShipping');

        EventHandling::onQuiqqerSalesOrdersSaveEnd($SalesOrder);
        self::assertTrue(true);
    }
}
