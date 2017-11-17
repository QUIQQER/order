<?php

/**
 * This file contains QUI\ERP\Order\EventHandling
 */

namespace QUI\ERP\Order;

use DusanKasan\Knapsack\Collection;
use QUI;
use Tracy\Debugger;

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

    /**
     * @param QUI\Rewrite $Rewrite
     * @param string $requestedUrl
     */
    public static function onRequest(QUI\Rewrite $Rewrite, $requestedUrl)
    {
        $path = trim(OrderProcess::getUrl(), '/');

        if (strpos($requestedUrl, $path) === false) {
            return;
        }

        if (strpos($requestedUrl, $path) !== 0) {
            return;
        }

        // order hash
        $hash = false;

        if (strpos($requestedUrl, '#')) {
            $hashParts = explode('#', $requestedUrl);

            if (isset($hashParts[1])) {
                $hash = $hashParts[1];
            }
        }

        $Process = new OrderProcess();
        $parts   = explode('/', $requestedUrl);

        if ($hash) {
            $Process->setAttribute('orderHash', $hash);
        }


        Debugger::barDump($hash, 'Order Hash');
        Debugger::barDump($parts, 'Order Parts');

        $Site = new QUI\Projects\Site\Virtual(array(
            'id'    => 1,
            'title' => 'Bestellungen',
            'name'  => 'Bestellung',
            'url'   => 'Bestellung'
        ), $Rewrite->getProject());

        $Site->setAttribute('layout', 'layout/noSidebar');
        $Site->setAttribute('content', $Process->create());

        $Rewrite->setSite($Site);
    }
}
