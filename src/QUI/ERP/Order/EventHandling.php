<?php

/**
 * This file contains QUI\ERP\Order\EventHandling
 */

namespace QUI\ERP\Order;

use DusanKasan\Knapsack\Collection;
use QUI;

/**
 * Class EventHandling
 *
 * @package QUI\ERP\Order
 */
class EventHandling
{
    /**
     * Add the add button to the products
     *
     * @param QUI\ERP\Products\Interfaces\ProductInterface $Product
     * @param Collection $Collection
     */
    public static function onQuiqqerProductsProductViewButtons(
        QUI\ERP\Products\Interfaces\ProductInterface $Product,
        Collection &$Collection
    ) {
        $Collection = $Collection->append(
            new QUI\ERP\Order\Controls\Buttons\ProductToBasket(array(
                'Product' => $Product
            ))
        );
    }
}
