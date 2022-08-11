<?php

/**
 * This file contains QUI\ERP\Order\Utils\Utils
 */

namespace QUI\ERP\Order\Utils;

use QUI;

use function is_array;

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
     * @throws QUI\ERP\Order\Exception
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
     * @throws QUI\ERP\Order\Exception
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
     * @throws QUI\ERP\Order\Exception
     */
    public static function getCheckout(QUI\Projects\Project $Project): QUI\Projects\Site
    {
        return self::getOrderProcess($Project);
    }

    /**
     * Return the url to the checkout / order process
     *
     * @param QUI\Projects\Project $Project
     * @param null|QUI\ERP\Order\Controls\AbstractOrderingStep $Step
     * @return string
     *
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
            $url = $url . '/' . $Step->getName();

            return $url;
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
        if (!($Order instanceof QUI\ERP\Order\Order) &&
            !($Order instanceof QUI\ERP\Order\OrderView) &&
            !($Order instanceof QUI\ERP\Order\OrderInProcess)) {
            return '';
        }

        try {
            return self::getOrderProcessUrlForHash($Project, $Order->getHash());
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
     */
    public static function getOrderProfileUrl(QUI\Projects\Project $Project, $Order): string
    {
        if (!($Order instanceof QUI\ERP\Order\Order) &&
            !($Order instanceof QUI\ERP\Order\OrderView) &&
            !($Order instanceof QUI\ERP\Order\OrderInProcess)) {
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
        } catch (QUI\Exception $Exception) {
            return '';
        }

        $ending = false;

        if (\strpos($url, '.html')) {
            $url    = \str_replace('.html', '', $url);
            $ending = true;
        }

        // parse the frontend users category
        $url .= '/erp/erp-order#' . $Order->getHash();

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
            $Config  = $Package->getConfig();
            $setting = $Config->getValue('order', 'prefix');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return \date('Y') . '-';
        }

        if ($setting === false) {
            return \date('Y') . '-';
        }

        $prefix = \strftime($setting);

        if (\mb_strlen($prefix) < 100) {
            return $prefix;
        }

        return \mb_substr($prefix, 0, 100);
    }

    /**
     * Can another payment method be chosen if the payment method does not work in an order?
     *
     * @param QUI\ERP\Accounting\Payments\Types\PaymentInterface $Payment
     * @return bool
     */
    public static function isPaymentChangeable(
        QUI\ERP\Accounting\Payments\Types\PaymentInterface $Payment
    ): bool {
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
        $Order = null
    ): QUI\ERP\Products\Product\ProductList {
        if (!is_array($products)) {
            $products = [];
        }

        foreach ($products as $productData) {
            if (!isset($productData['id'])) {
                continue;
            }

            $productId    = $productData['id'];
            $productClass = null;

            if (isset($productData['class'])) {
                $productClass = $productData['class'];
            }


            // bridge for text articles
            if ($productClass === QUI\ERP\Accounting\Invoice\Articles\Text::class
                || empty($productId)
                || $productId === '-') {
                $Product = new QUI\ERP\Products\Product\TextProduct($productData);

                try {
                    $List->addProduct($Product);
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::write($Exception->getMessage());
                }

                continue;
            }


            if ($productClass !== QUI\ERP\Accounting\Article::class &&
                $productClass !== null) {
                QUI\System\Log::writeRecursive('######### Basket product import');
                QUI\System\Log::writeRecursive('######### unknown article class for product');
                QUI\System\Log::writeRecursive($productData);
                continue;
            }


            try {
                // check if active
                $Real = QUI\ERP\Products\Handler\Products::getProduct($productData['id']);

                if (!$Real->isActive()) {
                    $message = QUI::getLocale()->get(
                        'quiqqer/order',
                        'order.process.product.not.available',
                        [
                            'title'     => $Real->getTitle(),
                            'articleNo' => $Real->getField(QUI\ERP\Products\Handler\Fields::FIELD_PRODUCT_NO)->getValue(
                            )
                        ]
                    );

                    if ($Order && \method_exists($Order, 'addFrontendMessage')) {
                        $Order->addFrontendMessage($message);
                    } else {
                        if (!QUI::getUsers()->isSystemUser(QUI::getUserBySession())) {
                            QUI::getMessagesHandler()->addAttention($message);
                        }
                    }

                    continue;
                }

                $Product = new QUI\ERP\Order\Basket\Product($productData['id'], $productData);

                if (isset($productData['quantity'])) {
                    $Product->setQuantity($productData['quantity']);
                }

                $List->addProduct($Product);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
                // @todo produkt existiert nicht, dummy product
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
        $newProductList  = [];
        $getProductIndex = function ($product) use (&$newProductList) {
            foreach ($newProductList as $index => $p) {
                $p1 = \serialize(self::getCompareProductArray($product));
                $p2 = \serialize(self::getCompareProductArray($p));

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
     * @throws \QUI\ERP\Products\Product\Exception
     * @throws \QUI\Exception
     */
    public static function isBasketProductEditable($product): bool
    {
        $productId = $product['id'];
        $Product   = QUI\ERP\Products\Handler\Products::getProduct($productId);
        $condition = QUI\ERP\Products\Utils\Products::getBasketCondition($Product);

        // TYPE_1 Kann ohne Einschränkung in den Warenkorb
        // TYPE_2 Kann nur alleine in den Warenkorb
        // TYPE_3 Kann mit anderen Produkten einmalig in den Warenkorb
        // TYPE_4 Kann mit anderen Produkten diesen Typs nicht in den Warenkorb
        // TYPE_5 Kann mit anderen Produkten diesen Typs einmalig in den Warenkorb

        if ($condition === QUI\ERP\Products\Field\Types\BasketConditions::TYPE_2
            || $condition === QUI\ERP\Products\Field\Types\BasketConditions::TYPE_3
            || $condition === QUI\ERP\Products\Field\Types\BasketConditions::TYPE_5
        ) {
            return false;
        }

        return true;
    }
}
