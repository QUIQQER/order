<?php

/**
 * This file contains QUI\ERP\Order\Utils\DataLayer
 */

namespace QUI\ERP\Order\Utils;

use QUI;
use QUI\ERP\Products\Handler\Fields;
use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Product\Product;
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
            'item_name' => $Product->getTitle(),
            'category' => $Product->getCategory()->getTitle(),
            'price' => $Product->getPrice()->getPrice(),
            'currency' => $Product->getPrice()->getCurrency()->getCode(),
            'manufacturer' => $manufacturer,
            'variant' => $variant
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

    public static function parseArticle(QUI\ERP\Accounting\Article $Article, null | QUI\Locale $Locale = null): array
    {
        try {
            $Product = Products::getProduct($Article->getId());
            $item = self::parseProduct($Product);
        } catch (QUI\Exception) {
            // text article
            $item = [
                'item_id' => '',
                'item_name' => $Article->getTitle(),
                'category' => '',
                'manufacturer' => '',
                'variant' => ''
            ];
        }

        $item['price'] = $Article->getPrice()->getValue();
        $item['currency'] = $Article->getPrice()->getCurrency()->getCode();
        $item['quantity'] = $Article->getQuantity();

        if ($Article->getDiscount()) {
            $item['discount'] = $Article->getDiscount()->getValue();
        }

        return $item;
    }

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
            'tax' => $tax
        ];

        if (class_exists('QUI\ERP\Shipping\Types\ShippingEntry') && $Order->getShipping()) {
            $order['shipping'] = $Order->getShipping()->getPrice();
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
