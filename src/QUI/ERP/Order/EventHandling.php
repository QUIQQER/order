<?php

/**
 * This file contains QUI\ERP\Order\EventHandling
 */

namespace QUI\ERP\Order;

use DusanKasan\Knapsack\Collection;
use QUI;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
        $hash  = false;
        $title = '/Bestellungen/';

        if (isset($_REQUEST['order'])) {
            $hash = $_REQUEST['order'];
        }

        $Process = new OrderProcess();
        $parts   = explode('/', $requestedUrl);

        if ($hash) {
            $Process->setAttribute('orderHash', $hash);
        }

        if (count($parts) > 2) {
            $Redirect = new RedirectResponse($title);
            $Redirect->setStatusCode(RedirectResponse::HTTP_BAD_REQUEST);

            echo $Redirect->getContent();
            $Redirect->send();
            exit;
        }

        Debugger::barDump($hash, 'Order Hash');
        Debugger::barDump($parts, 'Order Parts');
        Debugger::barDump($requestedUrl, 'Requested url');

        if ($Process->getOrder()) {
            Debugger::barDump($Process->getOrder()->getId(), 'ORDER ID');
        }

        if (isset($parts[1])) {
            $Process->setAttribute('step', $parts[1]);
        }

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
