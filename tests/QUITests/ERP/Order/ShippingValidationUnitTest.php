<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Order\Order;
use QUI\ERP\Shipping\Api\ShippingInterface;
use ReflectionClass;
use ReflectionProperty;

class ShippingValidationUnitTest extends TestCase
{
    public function testValidationUsesCurrentShippingApiMethods(): void
    {
        require_once dirname(__DIR__, 3)
            . '/phpstan-stubs/QUI/ERP/Shipping/Api/ShippingInterface.php';

        $Order = (new ReflectionClass(Order::class))->newInstanceWithoutConstructor();
        $Articles = new ArticleList();
        $Articles->addArticle(new Article());
        $this->setProperty($Order, 'Articles', $Articles);

        $Shipping = $this->getMockBuilder(ShippingInterface::class)
            ->onlyMethods(get_class_methods(ShippingInterface::class))
            ->addMethods(['setErpEntity', 'isValid', 'canUsedInErpEntity', 'canUsedBy'])
            ->getMock();
        $Shipping->expects(self::once())->method('setErpEntity')->with($Order);
        $Shipping->expects(self::once())->method('isValid')->willReturn(true);
        $Shipping->expects(self::once())->method('canUsedInErpEntity')->with($Order)->willReturn(false);
        $Shipping->expects(self::never())->method('canUsedBy');

        $this->expectException(QUI\Exception::class);
        $Order->validateShipping($Shipping);
    }

    private function setProperty(object $Object, string $name, mixed $value): void
    {
        $Property = new ReflectionProperty($Object, $name);
        $Property->setValue($Object, $value);
    }
}
