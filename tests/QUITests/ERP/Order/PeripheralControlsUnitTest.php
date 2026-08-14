<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\AbstractOrderProcessProvider;
use QUI\ERP\Order\Basket\Basket as BasketModel;
use QUI\ERP\Order\Controls\Basket\Basket as BasketControl;
use QUI\ERP\Order\Controls\Basket\Small;
use QUI\ERP\Order\Controls\Order\Order as OrderControl;
use QUI\ERP\Order\Controls\OrderProcess\Delivery;
use QUI\ERP\Order\Controls\OrderProcess\Finish;
use QUI\ERP\Order\Controls\OrderProcess\Processing;
use QUI\ERP\Order\Controls\OrderProcess\Registration;
use QUI\ERP\Order\Controls\Products\ProductList;
use QUI\ERP\Order\Console\SendOrderConfirmationMail;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderInProcess;
use QUI\Locale;
use QUI\Projects\Project;
use QUI\Interfaces\Projects\Site;
use QUI\Utils\Singleton;
use QUITests\ERP\Order\Fixtures\TestableHandler;
use ReflectionProperty;
use ReflectionMethod;

class PeripheralControlsUnitTest extends TestCase
{
    public function testSendOrderConfirmationConsoleToolCanBeRegistered(): void
    {
        self::assertInstanceOf(
            SendOrderConfirmationMail::class,
            new SendOrderConfirmationMail()
        );
    }

    public function testBasketControlMetadataAndValueSelection(): void
    {
        $Control = new BasketControl(['isLoading' => true]);
        $Basket = $this->createMock(BasketModel::class);
        $Project = $this->createMock(Project::class);
        $Control->setBasket($Basket);
        $Control->setProject($Project);

        self::assertTrue($Control->isLoading());
        self::assertSame('plain', $Control->getValueText('plain'));
        self::assertSame('', $Control->getValueText(['unavailable' => 'value']));
        self::assertSame(
            'localized',
            $Control->getValueText([\QUI::getLocale()->getCurrent() => 'localized'])
        );
        self::assertSame($Project, (new ReflectionMethod($Control, 'getProject'))->invoke($Control));
        self::assertIsBool($Control->isGuest());
    }

    public function testSmallBasketKeepsExplicitProject(): void
    {
        $Control = new Small();
        $Control->setBasket($this->createMock(BasketModel::class));
        $Project = $this->createMock(Project::class);
        $Control->setProject($Project);

        self::assertSame($Project, (new ReflectionMethod($Control, 'getProject'))->invoke($Control));
    }

    public function testProductListRendersEmptyAndMalformedSelections(): void
    {
        $Empty = new ProductList(['productsIds' => []]);
        $Malformed = new ProductList(['productsIds' => '{not-json']);

        self::assertIsString($Empty->getBody());
        self::assertIsString($Malformed->getBody());
        self::assertSame('section', $Empty->getAttribute('nodeName'));
    }

    public function testOrderControlReturnsAssignedOrder(): void
    {
        $Order = $this->createMock(Order::class);
        $Control = new OrderControl(['Order' => $Order]);

        self::assertSame($Order, $Control->getOrder());
        self::assertSame($Order, $Control->getOrder());
    }

    public function testFinishMetadataBodyAndValidationBranches(): void
    {
        $Finish = new Finish();
        self::assertSame('Finish', $Finish->getName());
        self::assertSame('fa-check', $Finish->getIcon());
        self::assertSame('', $Finish->getBody());
        $Finish->save();

        try {
            $Finish->validate();
            self::fail('Missing order must be rejected.');
        } catch (\QUI\ERP\Order\Exception) {
            self::assertTrue(true);
        }

        $Open = $this->createMock(OrderInProcess::class);
        $Open->method('isSuccessful')->willReturn(0);
        $Open->method('isPosted')->willReturn(false);
        $Finish->setAttribute('Order', $Open);

        try {
            $Finish->validate();
            self::fail('Unposted order must be rejected.');
        } catch (\QUI\ERP\Order\Exception) {
            self::assertTrue(true);
        }

        $Successful = $this->createMock(OrderInProcess::class);
        $Successful->method('isSuccessful')->willReturn(1);
        $Finish->setAttribute('Order', $Successful);
        $Finish->validate();

        $Posted = $this->createMock(OrderInProcess::class);
        $Posted->method('isSuccessful')->willReturn(0);
        $Posted->method('isPosted')->willReturn(true);
        $Finish->setAttribute('Order', $Posted);
        $Finish->validate();
        self::assertTrue(true);
    }

    public function testProcessingMetadataContentAndProviderRendering(): void
    {
        $Locale = $this->createMock(Locale::class);
        $Locale->method('get')->willReturnCallback(
            static fn(string $package, string $key): string => $key
        );
        $Processing = new Processing();

        self::assertSame('Processing', $Processing->getName());
        self::assertSame('fa-check', $Processing->getIcon());
        self::assertSame('', $Processing->getBody());
        self::assertStringContainsString('CheckoutPayment', $Processing->getContent($Locale));
        self::assertSame('ordering.step.title.Processing', $Processing->getTitle($Locale));
        $Processing->setTitle('Custom title');
        $Processing->setContent('Custom content');
        self::assertSame('Custom title', $Processing->getTitle($Locale));
        self::assertSame('Custom content', $Processing->getContent($Locale));
        $Processing->validate();
        $Processing->save();

        $Provider = $this->createMock(AbstractOrderProcessProvider::class);
        $Order = $this->createMock(AbstractOrder::class);
        $Provider->method('getDisplay')->willReturn('<p>Provider</p>');
        $Provider->method('hasErrors')->willReturn(false);
        $Processing->setAttribute('Order', $Order);
        $Processing->setProcessingProvider($Provider);

        self::assertIsString($Processing->getBody());
    }

    public function testProcessingProviderExceptionRendersError(): void
    {
        $Provider = $this->createMock(AbstractOrderProcessProvider::class);
        $Provider->method('getDisplay')->willThrowException(new \RuntimeException('failed'));
        $Processing = new Processing(['Order' => $this->createMock(AbstractOrder::class)]);
        $Processing->setProcessingProvider($Provider);

        self::assertIsString($Processing->getBody());
    }

    public function testRegistrationAndDeliveryRenderSimpleTemplates(): void
    {
        $Registration = new Registration();
        self::assertSame('Registration', $Registration->getName());
        self::assertTrue($Registration->hasOwnForm());
        self::assertIsString($Registration->getBody());
        $Registration->validate();
        $Registration->save();

        $Customer = $this->createMock(\QUI\ERP\User::class);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Delivery = new Delivery(['Order' => $Order]);
        self::assertSame('Delivery', $Delivery->getName());
        self::assertSame('fa-truck', $Delivery->getIcon());
        self::assertIsString($Delivery->getBody());
        $Delivery->validate();
        $Delivery->save();
    }

    public function testProductToBasketRendersDefaultAndUnavailableProduct(): void
    {
        $Default = new \QUI\ERP\Order\Controls\Buttons\ProductToBasket();
        self::assertIsString($Default->getBody());

        $Product = $this->createMock(\QUI\ERP\Products\Product\Product::class);
        $Product->method('getMaximumQuantity')->willReturn(0.0);
        $Product->method('getId')->willReturn(73);
        $Product->method('getTitle')->willReturn('Unavailable product');
        $Unavailable = new \QUI\ERP\Order\Controls\Buttons\ProductToBasket([
            'Product' => $Product,
            'btnText' => 'Add now'
        ]);

        self::assertIsString($Unavailable->getBody());
        self::assertTrue($Unavailable->getAttribute('disabled'));
        self::assertTrue($Unavailable->getAttribute('data-qui-options-disabled'));
        self::assertSame(73, $Unavailable->getAttribute('data-pid'));
    }

    public function testEmptyFrontendOrderListsRenderWithExplicitContext(): void
    {
        $Handler = new TestableHandler();
        $Handler->setResolvedUserOrders([]);
        $Instances = new ReflectionProperty(Singleton::class, 'instances');
        $original = $Instances->getValue();
        $instances = $original;
        $instances[\QUI\ERP\Order\Handler::class] = $Handler;
        $Instances->setValue(null, $instances);
        $Project = $this->createMock(Project::class);
        $Site = $this->createMock(Site::class);
        $User = $this->createMock(\QUI\Users\User::class);

        try {
            $Orders = new \QUI\ERP\Order\FrontendUsers\Controls\UserOrders([
                'Project' => $Project,
                'Site' => $Site,
                'User' => $User,
                'limit' => 0,
                'page' => 2
            ]);
            self::assertIsString($Orders->getBody());
            self::assertSame($Site, $Orders->getSite());
            $Orders->onSave();
            $Orders->validate();

            $Opened = new \QUI\ERP\Order\FrontendUsers\Controls\UserOpenedOrders([
                'Project' => $Project,
                'Site' => $Site
            ]);
            self::assertIsString($Opened->getBody());
        } finally {
            $Instances->setValue(null, $original);
        }
    }

    public function testFrontendOrderRejectsUnsupportedOrderInterface(): void
    {
        $Control = new \QUI\ERP\Order\FrontendUsers\Controls\UserOrders();
        $Unsupported = $this->createMock(\QUI\ERP\Order\OrderInterface::class);

        self::assertSame('', $Control->renderOrder($Unsupported));
    }

    public function testOrderPanelUtilitiesReturnStructuredResults(): void
    {
        self::assertIsArray(\QUI\ERP\Order\Utils\Panel::getOrderPackages());
        self::assertIsArray(\QUI\ERP\Order\Utils\Panel::getPanelCategories());
        self::assertIsString(\QUI\ERP\Order\Utils\Panel::getPanelCategory('phpunit-missing'));
    }

    public function testBasketControlsRenderConfiguredProductList(): void
    {
        $View = $this->createMock(\QUI\ERP\Products\Product\ProductListFrontendView::class);
        $View->method('toArray')->willReturn([
            'sum' => '0.00',
            'subSum' => '0.00',
            'products' => [],
            'attributes' => [],
            'vat' => []
        ]);
        $View->method('getProducts')->willReturn([]);
        $View->method('count')->willReturn(0);
        $Products = $this->createMock(\QUI\ERP\Products\Product\ProductList::class);
        $Products->method('getView')->willReturn($View);
        $Products->method('getProducts')->willReturn([]);
        $Basket = $this->createMock(BasketModel::class);
        $Basket->method('getProducts')->willReturn($Products);
        $Project = $this->createMock(Project::class);
        $Site = $this->createMock(Site::class);
        $Site->method('getUrlRewritten')->willReturn('/checkout');
        $Project->method('getSites')->willReturn([$Site]);

        $Large = new BasketControl();
        $Large->setBasket($Basket);
        $Large->setProject($Project);
        self::assertIsString($Large->getBody());

        $Small = new Small();
        $Small->setBasket($Basket);
        $Small->setProject($Project);
        self::assertIsString($Small->getBody());
    }

    public function testOrderProcessBasketEmptyStateAndMetadata(): void
    {
        $Basket = $this->createMock(BasketModel::class);
        $Basket->method('count')->willReturn(0);
        $Basket->method('getFrontendMessages')->willReturn(new \QUI\ERP\Comments());
        $Step = new \QUI\ERP\Order\Controls\OrderProcess\Basket(['Basket' => $Basket]);

        self::assertSame('Basket', $Step->getName());
        self::assertSame('fa fa-shopping-basket', $Step->getIcon());
        self::assertSame($Basket, $Step->getBasket());
        self::assertFalse($Step->showNext());
        self::assertIsString($Step->getBody());

        $this->expectException(\QUI\ERP\Order\Exception::class);
        $Step->validate();
    }

    public function testOrderControlRendersOrderInProcessView(): void
    {
        $InvoiceAddress = $this->createMock(\QUI\ERP\Address::class);
        $InvoiceAddress->method('getUUID')->willReturn('invoice-address');
        $DeliveryAddress = $this->createMock(\QUI\ERP\Address::class);
        $DeliveryAddress->method('getUUID')->willReturn('delivery-address');
        $Calculations = $this->createMock(\QUI\ERP\Accounting\Calculations::class);
        $Calculations->method('getVat')->willReturn([]);
        $Factors = $this->createMock(\QUI\ERP\Accounting\PriceFactors\FactorList::class);
        $Articles = $this->createMock(\QUI\ERP\Accounting\ArticleList::class);
        $Articles->method('getPriceFactors')->willReturn($Factors);
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getHash')->willReturn('render-order');
        $Order->method('hasInvoice')->willReturn(false);
        $Order->method('getInvoiceAddress')->willReturn($InvoiceAddress);
        $Order->method('getDeliveryAddress')->willReturn($DeliveryAddress);
        $Order->method('getArticles')->willReturn($Articles);
        $Order->method('getPriceCalculation')->willReturn($Calculations);
        $Order->method('getPayment')->willReturn(null);
        $Order->method('getShipping')->willReturn(null);
        $Control = new OrderControl([
            'Order' => $Order,
            'template' => 'OrderLikeBasket'
        ]);

        self::assertIsString($Control->getBody());
    }

    public function testUserOrderRenderingAndArticleRendering(): void
    {
        $Project = $this->createMock(Project::class);
        $Site = $this->createMock(Site::class);
        $Site->method('getUrlRewritten')->willReturn('/order-process');
        $Project->method('getSites')->willReturn([$Site]);
        $Control = new \QUI\ERP\Order\FrontendUsers\Controls\UserOrders([
            'Project' => $Project,
            'Site' => $Site
        ]);
        $Articles = $this->createMock(\QUI\ERP\Accounting\ArticleList::class);
        $Articles->method('getArticles')->willReturn([]);
        $Articles->method('toArray')->willReturn([]);
        $Order = $this->createMock(Order::class);
        $Order->method('getArticles')->willReturn($Articles);
        $Order->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => match ($key) {
                'paid_status' => \QUI\ERP\Constants::PAYMENT_STATUS_OPEN,
                'paid_date' => null,
                default => null
            }
        );
        $Order->method('hasInvoice')->willReturn(false);
        $Order->method('getProcessingStatus')->willReturn(null);
        $Order->method('getShippingStatus')->willReturn(null);
        $Order->method('getPayment')->willReturn(null);
        $Order->method('getHash')->willReturn('profile-order');

        self::assertIsString($Control->renderOrder($Order));

        $Article = $this->createMock(\QUI\ERP\Accounting\Article::class);
        $Article->method('getArticleNo')->willReturn('phpunit-missing-product');
        self::assertIsString($Control->renderArticle($Article));
    }
}
