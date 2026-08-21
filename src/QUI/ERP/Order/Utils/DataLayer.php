<?php

/**
 * This file contains QUI\ERP\Order\Utils\DataLayer
 */

namespace QUI\ERP\Order\Utils;

use QUI;
use QUI\ERP\Products\Handler\Fields;
use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Product\Product;
use QUI\ERP\Products\Product\ProductList;
use QUI\ERP\Products\Product\Types\VariantChild;

/**
 * Helper for DataLayer Data
 *
 * item_id: "SKU_12345",
 * item_name: "Stan and Friends Tee",
 * affiliation: "Google Merchandise Store",
 * coupon: "SUMMER_FUN",
 * discount: 2.22,
 * index: 0,
 * item_brand: "Google",
 * item_category: "Apparel",
 * item_category2: "Adult",
 * item_category3: "Shirts",
 * item_category4: "Crew",
 * item_category5: "Short sleeve",
 * item_list_id: "related_products",
 * item_list_name: "Related Products",
 * item_variant: "green",
 * location_id: "ChIJIQBpAG2ahYAR_6128GcTUEo",
 * price: 9.99,
 * quantity: 1
 */
class DataLayer
{
    /**
     * @param QUI\Locale|null $Locale
     * @return array<string, mixed>
     */
    public static function parseProduct(Product $Product, $Locale = null): array
    {
        $manufacturer = '';
        $variant = '';

        $mField = $Product->getField(Fields::FIELD_MANUFACTURER)->getValue();

        if (!empty($mField) && isset($mField[0])) {
            try {
                $manufacturer = QUI::getUsers()->get($mField[0])->getName();
            } catch (QUI\Exception) {
            }
        }

        if ($Product instanceof VariantChild) {
            $variant = $Product->generateVariantHash();
        }

        $product = [
            'item_id' => $Product->getField(Fields::FIELD_PRODUCT_NO)->getValue(),
            'item_name' => $Product->getTitle($Locale),
            'item_brand' => $manufacturer,
            'item_category' => $Product->getCategory()?->getTitle($Locale) ?? '',
            'item_variant' => $variant,
            'price' => $Product->getPrice()->getPrice(),
        ];

        // categories
        $categories = $Product->getCategories();
        $i = 2; // google start the second category at 2

        foreach ($categories as $Category) {
            /* @var $Category QUI\ERP\Products\Category\Category */
            $product['item_category' . $i] = $Category->getTitle($Locale);
            $i++;
        }

        return $product;
    }

    /**
     * @param QUI\Locale|null $Locale
     * @return array<string, mixed>
     */
    public static function parseProductEvent(Product $Product, $Locale = null): array
    {
        $price = $Product->getPrice()->getPrice();
        $item = self::parseProduct($Product, $Locale);
        $item['quantity'] = 1;

        return [
            'currency' => $Product->getPrice()->getCurrency()->getCode(),
            'value' => $price,
            'items' => [$item]
        ];
    }

    /**
     * @param array<int, int|string> $productIds
     * @param QUI\Locale|null $Locale
     * @return array<int, array<string, mixed>>
     */
    public static function parseProductItems(array $productIds, $Locale = null, int $startIndex = 0): array
    {
        $items = [];
        $productIds = array_slice($productIds, 0, 100);

        foreach ($productIds as $position => $productId) {
            try {
                $Product = Products::getProduct((int)$productId);
            } catch (QUI\Exception) {
                continue;
            }

            $item = self::parseProduct($Product, $Locale);
            $item['index'] = $startIndex + $position;
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param QUI\Locale|null $Locale
     * @return array<string, mixed>
     * @throws QUI\Exception
     */
    public static function parseProductList(ProductList $List, $Locale = null): array
    {
        $list = $List->toArray($Locale);
        $items = [];

        foreach ($list['products'] as $productData) {
            try {
                $Product = Products::getProduct((int)$productData['id']);
            } catch (QUI\Exception) {
                continue;
            }

            $item = self::parseProduct($Product, $Locale);
            $item['quantity'] = $productData['quantity'] ?? 1;

            if (isset($productData['calculated_price'])) {
                $item['price'] = $productData['calculated_price'];
            }

            $items[] = $item;
        }

        return [
            'currency' => $List->getCurrency()->getCode(),
            'value' => $list['sum'],
            'items' => $items
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function parseArticle(
        QUI\ERP\Accounting\ArticleInterface $Article,
        null | QUI\Locale $Locale = null
    ): array {
        try {
            $Product = Products::getProduct($Article->getId());
            $item = self::parseProduct($Product, $Locale);
        } catch (QUI\Exception) {
            // text article
            $item = [
                'item_id' => '',
                'item_name' => $Article->getTitle(),
                'item_brand' => '',
                'item_category' => '',
                'item_variant' => ''
            ];
        }

        $item['price'] = $Article->getPrice()->getValue();
        $item['quantity'] = $Article->getQuantity();

        if ($Article->getDiscount()) {
            $item['discount'] = $Article->getDiscount()->getValue();
        }

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    public static function parseOrder(QUI\ERP\Order\OrderInterface $Order, null | QUI\Locale $Locale = null): array
    {
        $calculations = $Order->getArticles()->getCalculations();
        $tax = 0;

        foreach ($calculations['vatArray'] as $vat) {
            $tax = $tax + $vat['sum'];
        }

        $order = [
            'currency' => $Order->getCurrency()->getCode(),
            'value' => $calculations['sum'],
            'tax' => $tax,
            'items' => []
        ];

        $Payment = $Order->getPayment();

        if ($Payment) {
            $order['payment_type'] = $Payment->getTitle($Locale);
        }

        if (class_exists('QUI\ERP\Shipping\Types\ShippingEntry')) {
            $Shipping = $Order->getShipping();

            if ($Shipping) {
                $order['shipping'] = $Shipping->getPrice();
                $order['shipping_tier'] = $Shipping->getTitle($Locale);
            }
        }

        if (QUI::getPackageManager()->isInstalled('quiqqer/coupons')) {
            $order['coupon'] = $Order->getDataEntry('quiqqer-coupons');
        }

        if ($Order->isSuccessful()) {
            $order['transaction_id'] = $Order->getUUID();
        }

        // items / articles
        $index = 0;

        foreach ($Order->getArticles() as $Article) {
            $article = self::parseArticle($Article, $Locale);
            $article['index'] = $index;

            $order['items'][] = $article;
            $index++;
        }

        return $order;
    }
}
