<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\ErpEntityInterface;
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

        $Shipping = new class implements ShippingInterface {
            public ?ErpEntityInterface $Entity = null;

            public function getId(): int
            {
                return 1;
            }

            public function setErpEntity(ErpEntityInterface $Entity): void
            {
                $this->Entity = $Entity;
            }

            public function isValid(): bool
            {
                return true;
            }

            public function canUsedInErpEntity(ErpEntityInterface $Entity): bool
            {
                return false;
            }

            public function canUsedBy(mixed $User, ErpEntityInterface $Entity): bool
            {
                return true;
            }
        };

        try {
            $Order->validateShipping($Shipping);
            self::fail('An invalid shipping entry must be rejected.');
        } catch (QUI\Exception) {
            self::assertSame($Order, $Shipping->Entity);
        }
    }

    private function setProperty(object $Object, string $name, mixed $value): void
    {
        $Property = new ReflectionProperty($Object, $name);
        $Property->setValue($Object, $value);
    }
}
