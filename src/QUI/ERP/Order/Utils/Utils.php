<?php

/**
 * This file contains QUI\ERP\Order\Utils\Utils
 */

namespace QUI\ERP\Order\Utils;

use QUI;
use QUI\Database\Exception;
use QUI\ERP\Accounting\Payments\Types\PaymentInterface;
use QUI\ERP\Products\Field\Types\BasketConditions;
use QUI\Projects\Project;

use function date;
use function is_array;
use function mb_strlen;
use function mb_substr;
use function method_exists;
use function serialize;
use function str_replace;
use function strpos;

/**
 * Class Utils
 * Helper to get some stuff (urls and information's) easier for the the order
 *
 * @package QUI\ERP\Order\Utils
 */
class Utils
{
    /**
     * @var null|string
     */
    protected static ?string $url = null;

    /**
     * Return the url to the checkout / order process
     *
     * @param QUI\Projects\Project $Project
     * @return QUI\Projects\Site
     *
     * @throws QUI\ERP\Order\Exception|QUI\Database\Exception
     */
    public static function getOrderProcess(QUI\Projects\Project $Project): QUI\Projects\Site
    {
        $sites = $Project->getSites([
            'where' => [
                'type' => 'quiqqer/order:types/orderingProcess'
            ],
            'limit' => 1
        ]);

        if (isset($sites[0])) {
            return $sites[0];
        }

        throw new QUI\ERP\Order\Exception([
            'quiqqer/order',
            'exception.order.process.not.found'
        ]);
    }

    /**
     * Return the shopping cart site object
     *
     * @param QUI\Projects\Project $Project
     * @return QUI\Projects\Site
     *
     * @throws QUI\ERP\Order\Exception|QUI\Database\Exception
     */
    public static function getShoppingCart(QUI\Projects\Project $Project): QUI\Projects\Site
    {
        $sites = $Project->getSites([
            'where' => [
                'type' => 'quiqqer/order:types/shoppingCart'
            ],
            'limit' => 1
        ]);

        if (isset($sites[0])) {
            return $sites[0];
        }

        throw new QUI\ERP\Order\Exception([
            'quiqqer/order',
            'exception.order.process.not.found'
        ]);
    }

    /**
     * @param QUI\Projects\Project $Project
     * @return QUI\Projects\Site
     *
     * @throws QUI\ERP\Order\Exception|QUI\Database\Exception
     */
    public static function getCheckout(QUI\Projects\Project $Project): QUI\Projects\Site
    {
        return self::getOrderProcess($Project);
    }

    /**
     * Return the url to the checkout / order process
     *
     * @param Project $Project
     * @param null $Step
     * @return string|null
     *
     * @throws Exception
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     */
    public static function getOrderProcessUrl(QUI\Projects\Project $Project, $Step = null): ?string
    {
        if (self::$url === null) {
            self::$url = self::getOrderProcess($Project)->getUrlRewritten();
        }

        if ($Step instanceof QUI\ERP\Order\Controls\AbstractOrderingStep) {
            $url = self::$url;
            return $url . '/' . $Step->getName();
        }

        return self::$url;
    }

    /**
     * @param QUI\Projects\Project $Project
     * @param $hash
     * @return string
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     */
    public static function getOrderProcessUrlForHash(QUI\Projects\Project $Project, $hash): string
    {
        $url = self::getOrderProcessUrl($Project);

        return $url . '/Order/' . $hash;
    }

    /**
     * @param QUI\Projects\Project $Project
     * @param QUI\ERP\Order\OrderInterface $Order
     *
     * @return string
     */
    public static function getOrderUrl(QUI\Projects\Project $Project, $Order): string
    {
        if (
            !($Order instanceof QUI\ERP\Order\Order) &&
            !($Order instanceof QUI\ERP\Order\OrderView) &&
            !($Order instanceof QUI\ERP\Order\OrderInProcess)
        ) {
            return '';
        }

        try {
            return self::getOrderProcessUrlForHash($Project, $Order->getUUID());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return '';
    }

    /**
     * @param QUI\Projects\Project $Project
     * @param QUI\ERP\Order\OrderInterface $Order
     *
     * @return string
     * @throws Exception
     */
    public static function getOrderProfileUrl(
        QUI\Projects\Project $Project,
        QUI\ERP\Order\OrderInterface $Order
    ): string {
        if (
            !($Order instanceof QUI\ERP\Order\Order) &&
            !($Order instanceof QUI\ERP\Order\OrderView) &&
            !($Order instanceof QUI\ERP\Order\OrderInProcess)
        ) {
            return '';
        }

        $sites = $Project->getSites([
            'where' => [
                'type' => 'quiqqer/frontend-users:types/profile'
            ],
            'limit' => 1
        ]);

        if (!isset($sites[0])) {
            return '';
        }

        /* @var $Site QUI\Projects\Site */
        $Site = $sites[0];

        try {
            $url = $Site->getUrlRewritten();
        } catch (QUI\Exception) {
            return '';
        }

        $ending = false;

        if (strpos($url, '.html')) {
            $url = str_replace('.html', '', $url);
            $ending = true;
        }

        // parse the frontend users category
        $url .= '/erp/erp-order#' . $Order->getUUID();

        if ($ending) {
            $url .= '.html';
        }

        return $url;
    }

    /**
     * Return the order prefix for every order / order in process
     *
     * @return string
     */
    public static function getOrderPrefix(): string
    {
        try {
            $Package = QUI::getPackage('quiqqer/order');
            $Config = $Package->getConfig();
            $setting = $Config->getValue('order', 'prefix');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return date('Y') . '-';
        }

        if ($setting === false) {
            return date('Y') . '-';
        }

        $prefix = \PHP81_BC\strftime($setting);

        if (mb_strlen($prefix) < 100) {
            return $prefix;
        }

        return mb_substr($prefix, 0, 100);
    }

    /**
     * Can another payment method be chosen if the payment method does not work in an order?
     *
     * @param PaymentInterface|null $Payment
     * @return bool
     */
    public static function isPaymentChangeable(
        ?QUI\ERP\Accounting\Payments\Types\PaymentInterface $Payment
    ): bool {
        if (!$Payment) {
            return true;
        }

        $Settings = QUI\ERP\Order\Settings::getInstance();

        return (bool)$Settings->get('paymentChangeable', $Payment->getId());
    }

    /**
     * @param QUI\ERP\Products\Product\ProductList $List
     * @param array $products
     * @param null|QUI\ERP\Order\AbstractOrder|QUI\ERP\Order\Basket\Basket $Order - optional, to add messages to the order if needed
     *
     * @return QUI\ERP\Products\Product\ProductList
     */
    public static function importProductsToBasketList(
        QUI\ERP\Products\Product\ProductList $List,
        array $products = [],
        null | QUI\ERP\Order\AbstractOrder | QUI\ERP\Order\Basket\Basket $Order = null
    ): QUI\ERP\Products\Product\ProductList {
        if (!is_array($products)) {
            $products = [];
        }

        $count = count($products);

        foreach ($products as $productData) {
            if (!isset($productData['id'])) {
                continue;
            }

            $productId = $productData['id'];
            $productClass = null;

            if (isset($productData['class'])) {
                $productClass = $productData['class'];
            }


            // bridge for text articles
            if (
                $productClass === QUI\ERP\Accounting\Invoice\Articles\Text::class
                || empty($productId)
                || $productId === -1
            ) {
                $Product = new QUI\ERP\Products\Product\TextProduct($productData);

                try {
                    $List->addProduct($Product);
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::write($Exception->getMessage());
                }

                continue;
            }


            if (
                $productClass !== QUI\ERP\Accounting\Article::class &&
                $productClass !== null
            ) {
                QUI\System\Log::writeRecursive('######### Basket product import');
                QUI\System\Log::writeRecursive('######### unknown article class for product');
                QUI\System\Log::writeRecursive($productData);
                continue;
            }


            try {
                // check if active
                $Real = QUI\ERP\Products\Handler\Products::getProduct((int)$productData['id']);

                if (!$Real->isActive()) {
                    $message = QUI::getLocale()->get(
                        'quiqqer/order',
                        'order.process.product.not.available',
                        [
                            'title' => $Real->getTitle(),
                            'articleNo' => $Real->getField(QUI\ERP\Products\Handler\Fields::FIELD_PRODUCT_NO)->getValue(
                            )
                        ]
                    );

                    if ($Order && method_exists($Order, 'addFrontendMessage')) {
                        $Order->addFrontendMessage($message);
                    } else {
                        if (!QUI::getUsers()->isSystemUser(QUI::getUserBySession())) {
                            QUI::getMessagesHandler()->addAttention($message);
                        }
                    }

                    continue;
                }

                $Product = new QUI\ERP\Order\Basket\Product($productData['id'], $productData);
                $condition = QUI\ERP\Products\Utils\Products::getBasketCondition($Product);

                if (
                    $condition === BasketConditions::TYPE_2 ||
                    $condition === BasketConditions::TYPE_6
                ) {
                    // if several products are to be imported and a Type2 and Type6 are to be imported.
                    // this product is ignored and not imported
                    if ($count >= 2) {
                        continue;
                    }
                }

                if (
                    $condition === BasketConditions::TYPE_2 ||
                    $condition === BasketConditions::TYPE_3 ||
                    $condition === BasketConditions::TYPE_5
                ) {
                    // quantity stays at 1
                    $Product->setQuantity(1);
                } elseif (isset($productData['quantity'])) {
                    $Product->setQuantity($productData['quantity']);
                }

                $List->addProduct($Product);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        return $List;
    }

    /**
     * Return a product array with all important fields, to compare a product with another
     *
     * @param $product
     * @return array
     */
    public static function getCompareProductArray($product): array
    {
        $compare = [];
        $needles = [
            'id',
            'title',
            'articleNo',
            'description',
            'unitPrice',
            'displayPrice',
            'class',
            'customFields',
            'customData',
            'display_unitPrice'
        ];

        foreach ($needles as $f) {
            if (isset($product[$f])) {
                $compare[$f] = $product[$f];
            }
        }

        return $compare;
    }

    /**
     * Takes a product array and brings together all products that can be brought together
     *
     * @param $products
     * @return array
     */
    public static function getMergedProductList($products): array
    {
        $newProductList = [];
        $getProductIndex = function ($product) use (&$newProductList) {
            // @phpstan-ignore-next-line
            foreach ($newProductList as $index => $p) {
                $p1 = serialize(self::getCompareProductArray($product));
                $p2 = serialize(self::getCompareProductArray($p));

                if ($p1 == $p2) {
                    return $index;
                }
            }

            return false;
        };

        foreach ($products as $product) {
            $index = $getProductIndex($product);

            if ($index !== false) {
                $newProductList[$index]['quantity'] = $newProductList[$index]['quantity'] + $product['quantity'];
                continue;
            }

            $newProductList[] = $product;
        }

        return $newProductList;
    }

    /**
     * @param $product
     * @return bool
     */
    public static function isBasketProductEditable($product): bool
    {
        try {
            $productId = $product['id'];
            $Product = QUI\ERP\Products\Handler\Products::getProduct((int)$productId);
            $condition = QUI\ERP\Products\Utils\Products::getBasketCondition($Product);
        } catch (QUI\Exception) {
            return false;
        }

        // TYPE_1 Kann ohne Einschränkung in den Warenkorb
        // TYPE_2 Kann nur alleine und nur einmalig in den Warenkorb
        // TYPE_3 Kann mit anderen Produkten einmalig in den Warenkorb
        // TYPE_4 Kann mit anderen Produkten diesen Typs nicht in den Warenkorb
        // TYPE_5 Kann mit anderen Produkten diesen Typs einmalig in den Warenkorb
        // TYPE_6 Kann nur alleine und mehrmalig in den Warenkorb

        if (
            $condition === QUI\ERP\Products\Field\Types\BasketConditions::TYPE_2
            || $condition === QUI\ERP\Products\Field\Types\BasketConditions::TYPE_3
            || $condition === QUI\ERP\Products\Field\Types\BasketConditions::TYPE_5
        ) {
            return false;
        }

        return true;
    }
}
