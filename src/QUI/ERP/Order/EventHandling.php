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
     * @param $ProductControl
     */
    public static function onQuiqqerProductsProductViewButtons(
        QUI\ERP\Products\Interfaces\ProductInterface $Product,
        Collection &$Collection,
        $ProductControl = null
    ) {
        $Button = new QUI\ERP\Order\Controls\Buttons\ProductToBasket([
            'Product' => $Product
        ]);

        if ($ProductControl
            && $ProductControl->existsAttribute('data-qui-option-available')
            && $ProductControl->getAttribute('data-qui-option-available') === false) {
            $Button->setAttribute('disabled', true);
        }

        $Collection = $Collection->append($Button);
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
        if (\defined('QUIQQER_AJAX')) {
            return;
        }

        try {
            $Project      = $Rewrite->getProject();
            $CheckoutSite = QUI\ERP\Order\Utils\Utils::getOrderProcess($Project);
            $path         = \trim($CheckoutSite->getUrlRewritten(), '/');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return;
        }

        if (\strpos($requestedUrl, $path) === false) {
            return;
        }

        if (\strpos($requestedUrl, $path) !== 0) {
            return;
        }

        // order hash
        $parts = \explode('/', $requestedUrl);

        if (\count($parts) > 2) {
            // load order
            $orderHash = $parts[2];

            try {
                $OrderProcess = new OrderProcess([
                    'orderHash' => $orderHash
                ]);
            } catch (QUI\Exception $Exception) {
                $Redirect = new RedirectResponse($CheckoutSite->getUrlRewritten());
                $Redirect->setStatusCode(RedirectResponse::HTTP_NOT_FOUND);

                // @todo weiterleiten zu richtiger fehler seite
                echo $Redirect->getContent();
                $Redirect->send();
                exit;
            }


            $Processing = new Controls\OrderProcess\Processing();

            $steps   = \array_keys($OrderProcess->getSteps());
            $steps[] = 'Order';
            $steps[] = $Processing->getName();
            $steps   = \array_flip($steps);

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
        $Order = null;

        try {
            $Order = Handler::getInstance()->getOrderByGlobalProcessId(
                $Transaction->getGlobalProcessId()
            );
        } catch (QUI\Exception $Exception) {
        }

        if ($Order === null && $Transaction->getHash() !== '') {
            try {
                $Order = Handler::getInstance()->getOrderByHash(
                    $Transaction->getHash()
                );
            } catch (QUI\Exception $Exception) {
            }

            if ($Order === null) {
                try {
                    $Order = Handler::getInstance()->getOrderInProcessByHash(
                        $Transaction->getHash()
                    );
                } catch (QUI\Exception $Exception) {
                }
            }
        }

        if ($Order === null) {
            return;
        }

        try {
            $Order->addTransaction($Transaction);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * @param Transaction $Transaction
     */
    public static function onTransactionStatusChange(Transaction $Transaction)
    {
        $Order = null;

        try {
            $Order = Handler::getInstance()->getOrderByGlobalProcessId(
                $Transaction->getGlobalProcessId()
            );
        } catch (QUI\Exception $Exception) {
        }

        if ($Order === null && $Transaction->getHash() !== '') {
            try {
                $Order = Handler::getInstance()->getOrderByHash(
                    $Transaction->getHash()
                );
            } catch (QUI\Exception $Exception) {
            }

            if ($Order === null) {
                try {
                    $Order = Handler::getInstance()->getOrderInProcessByHash(
                        $Transaction->getHash()
                    );
                } catch (QUI\Exception $Exception) {
                }
            }
        }

        if ($Order === null) {
            return;
        }

        try {
            $Order->setAttribute('paid_status', Order::PAYMENT_STATUS_OPEN);
            $Order->calculatePayments();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * @param QUI\ERP\Accounting\Invoice\InvoiceTemporary $InvoiceTemporary
     * @param QUI\ERP\Accounting\Invoice\Invoice $Invoice
     */
    public static function onQuiqqerInvoiceTemporaryInvoicePostEnd(
        QUI\ERP\Accounting\Invoice\InvoiceTemporary $InvoiceTemporary,
        QUI\ERP\Accounting\Invoice\Invoice $Invoice
    ) {
        try {
            $Order = Handler::getInstance()->getOrderByHash($Invoice->getHash());

            if ($Order->isPosted()) {
                return;
            }

            QUI::getDataBase()->update(
                Handler::getInstance()->table(),
                ['invoice_id' => $Invoice->getCleanId()],
                ['id' => $Order->getId()]
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    /**
     * event: order creation
     *
     * @param Order $Order
     * @throws QUI\Exception
     */
    public static function onQuiqqerOrderCreated(Order $Order)
    {
        if (Settings::getInstance()->get('order', 'sendOrderConfirmation')) {
            Mail::sendOrderConfirmationMail($Order);
        }
    }

    /**
     * @param QUI\Package\Package $Package
     * @throws QUI\Exception
     */
    public static function onPackageSetup(QUI\Package\Package $Package)
    {
        if ($Package->getName() !== 'quiqqer/order') {
            return;
        }

        // create invoice payment status
        $Handler = ProcessingStatus\Handler::getInstance();
        $Factory = ProcessingStatus\Factory::getInstance();
        $list    = $Handler->getList();

        if (!empty($list)) {
            return;
        }

        $languages = QUI::availableLanguages();

        $getLocaleTranslations = function ($key) use ($languages) {
            $result = [];

            foreach ($languages as $language) {
                $result[$language] = QUI::getLocale()->getByLang($language, 'quiqqer/order', $key);
            }

            return $result;
        };


        // Neu
        $Factory->createProcessingStatus(1, '#ff8c00', $getLocaleTranslations('processing.status.default.1'));

        // In Bearbeitung
        $Factory->createProcessingStatus(2, '#9370db', $getLocaleTranslations('processing.status.default.2'));

        // Bearbeitet
        $Factory->createProcessingStatus(3, '#38b538', $getLocaleTranslations('processing.status.default.3'));

        // Erledigt
        $Factory->createProcessingStatus(4, '#228b22', $getLocaleTranslations('processing.status.default.4'));

        // storniert
        $Factory->createProcessingStatus(5, '#adadad', $getLocaleTranslations('processing.status.default.5'));
    }
}
