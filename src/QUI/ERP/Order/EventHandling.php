<?php

/**
 * This file contains QUI\ERP\Order\EventHandling
 */

namespace QUI\ERP\Order;

use DusanKasan\Knapsack\Collection;
use Symfony\Component\HttpFoundation\RedirectResponse;

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

        // order hash
        $parts = explode('/', $requestedUrl);

        if (count($parts) > 2) {
            // load order
            $orderHash = $parts[2];

            try {
                $OrderProcess = new OrderProcess(array(
                    'orderHash' => $orderHash
                ));
            } catch (QUI\Exception $Exception) {
                $Redirect = new RedirectResponse($CheckoutSite->getUrlRewritten());
                $Redirect->setStatusCode(RedirectResponse::HTTP_NOT_FOUND);

                // @todo weiterleiten zu richtiger fehler seite
                echo $Redirect->getContent();
                $Redirect->send();
                exit;
            }


            $steps   = array_keys($OrderProcess->getSteps());
            $steps[] = 'Order';
            $steps   = array_flip($steps);

            if (!isset($parts[1]) || !isset($steps[$parts[1]]) || !isset($parts[2])) {
                $Redirect = new RedirectResponse($CheckoutSite->getUrlRewritten());
                $Redirect->setStatusCode(RedirectResponse::HTTP_BAD_REQUEST);

                echo $Redirect->getContent();
                $Redirect->send();
                exit;
            }

            $CheckoutSite->setAttribute('order::step', $parts[1]);


            try {
                $Order = Handler::getInstance()->getOrderByHash($orderHash);
                $CheckoutSite->setAttribute('order::hash', $Order->getHash());
            } catch (QUI\Exception $Exception) {
                QUI::getGlobalResponse()->setStatusCode(RedirectResponse::HTTP_NOT_FOUND);

                // @todo weiterleiten zu fehler seite
            }

            $Rewrite->setSite($CheckoutSite);

            return;
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
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }
}
