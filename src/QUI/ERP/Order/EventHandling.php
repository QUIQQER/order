<?php

/**
 * This file contains QUI\ERP\Order\EventHandling
 */

namespace QUI\ERP\Order;

use DusanKasan\Knapsack\Collection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tracy\Debugger;

use QUI;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;

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
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws Basket\Exception
     */
    public static function onRequest(QUI\Rewrite $Rewrite, $requestedUrl)
    {
        $Project      = $Rewrite->getProject();
        $CheckoutSite = QUI\ERP\Order\Utils\Utils::getOrderProcess($Project);
        $path         = trim($CheckoutSite->getUrlRewritten(), '/');

        if (strpos($requestedUrl, $path) === false) {
            return;
        }

        if (strpos($requestedUrl, $path) !== 0) {
            return;
        }

        // @todo order loading

        // order hash
        $parts = explode('/', $requestedUrl);

        if (count($parts) > 2) {
            $Redirect = new RedirectResponse($CheckoutSite->getAttribute('title'));
            $Redirect->setStatusCode(RedirectResponse::HTTP_BAD_REQUEST);

            echo $Redirect->getContent();
            $Redirect->send();
            exit;
        }


        if (isset($parts[1])) {
            $CheckoutSite->setAttribute('order::step', $parts[1]);
        }

        $Rewrite->setSite($CheckoutSite);
    }

    /**
     * event: on transaction create
     * assign to the order
     *
     * @param Transaction $Transaction
     */
    public static function onTransactionCreate(Transaction $Transaction)
    {
        $hash = $Transaction->getHash();

        try {
            $Order = Handler::getInstance()->getOrderByHash($hash);
            $Order->addTransaction($Transaction);
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }
}
