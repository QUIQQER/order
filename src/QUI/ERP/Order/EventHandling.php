<?php

/**
 * This file contains QUI\ERP\Order\EventHandling
 */

namespace QUI\ERP\Order;

use DusanKasan\Knapsack\Collection;
use Exception;
use QUI;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Order\Controls\OrderProcess\CustomerData;
use QUI\Smarty\Collector;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

use function array_flip;
use function array_keys;
use function class_exists;
use function count;
use function defined;
use function explode;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function ltrim;
use function mb_strpos;
use function parse_url;
use function strtotime;
use function trim;

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
    ): void {
        $Button = new QUI\ERP\Order\Controls\Buttons\ProductToBasket([
            'Product' => $Product
        ]);

        if (
            $ProductControl
            && $ProductControl->existsAttribute('data-qui-option-available')
            && $ProductControl->getAttribute('data-qui-option-available') === false
        ) {
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
    public static function onRequest(QUI\Rewrite $Rewrite, string $requestedUrl): void
    {
        if (defined('QUIQQER_AJAX')) {
            return;
        }

        try {
            $Project = $Rewrite->getProject();
            $CheckoutSite = QUI\ERP\Order\Utils\Utils::getOrderProcess($Project);
            $path = trim($CheckoutSite->getUrlRewritten(), '/');

            if (mb_strpos($path, 'http') === 0) {
                $path = parse_url($path);
                $path = ltrim($path['path'], '/');
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return;
        }

        if (!str_contains($requestedUrl, $path)) {
            return;
        }

        if (!str_starts_with($requestedUrl, $path)) {
            return;
        }

        // order hash
        $parts = explode('/', $requestedUrl);

        if (count($parts) > 2) {
            // load order
            $orderHash = $parts[2];

            try {
                $OrderProcess = new OrderProcess([
                    'orderHash' => $orderHash
                ]);
            } catch (QUI\Exception) {
                $Redirect = new RedirectResponse($CheckoutSite->getUrlRewritten());
                $Redirect->setStatusCode(Response::HTTP_NOT_FOUND);

                // @todo weiterleiten zu richtiger fehler seite
                echo $Redirect->getContent();
                $Redirect->send();
                exit;
            }

            if (QUI::getUsers()->isNobodyUser(QUI::getUserBySession())) {
                try {
                    $Order = Handler::getInstance()->getOrderByHash($orderHash);

                    // if order is finished & an order in process & the user is nobody
                    // we need to create the order
                    if ($Order instanceof OrderInProcess && $Order->isSuccessful()) {
                        $OrderInProcess = $Order;
                        $Order = $Order->createOrder(QUI::getUsers()->getSystemUser());

                        $OrderInProcess->delete(QUI::getUsers()->getSystemUser());
                    }

                    $CheckoutSite->setAttribute('order::hash', $Order->getUUID());
                    $Rewrite->setSite($CheckoutSite);

                    return;
                } catch (QUI\Exception) {
                }
            }

            $Processing = new Controls\OrderProcess\Processing();

            $steps = array_keys($OrderProcess->getSteps());
            $steps[] = 'Order';
            $steps[] = $Processing->getName();
            $steps = array_flip($steps);

            if (!isset($parts[1]) || !isset($steps[$parts[1]]) || !isset($parts[2])) {
                $Redirect = new RedirectResponse($CheckoutSite->getUrlRewritten());
                $Redirect->setStatusCode(Response::HTTP_BAD_REQUEST);

                echo $Redirect->getContent();
                $Redirect->send();
                exit;
            }

            $CheckoutSite->setAttribute('order::step', $parts[1]);


            try {
                $Order = Handler::getInstance()->getOrderByHash($orderHash);
                $CheckoutSite->setAttribute('order::hash', $Order->getUUID());
            } catch (QUI\Exception) {
                QUI::getGlobalResponse()->setStatusCode(Response::HTTP_NOT_FOUND);
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
    public static function onTransactionCreate(Transaction $Transaction): void
    {
        $Order = null;

        try {
            $Order = Handler::getInstance()->getOrderByGlobalProcessId(
                $Transaction->getGlobalProcessId()
            );
        } catch (QUI\Exception) {
        }

        if ($Order === null && $Transaction->getHash() !== '') {
            try {
                $Order = Handler::getInstance()->getOrderByHash(
                    $Transaction->getHash()
                );
            } catch (QUI\Exception) {
            }

            if ($Order === null) {
                try {
                    $Order = Handler::getInstance()->getOrderInProcessByHash(
                        $Transaction->getHash()
                    );
                } catch (QUI\Exception) {
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
    public static function onTransactionStatusChange(Transaction $Transaction): void
    {
        $Order = null;

        try {
            $Order = Handler::getInstance()->getOrderByGlobalProcessId(
                $Transaction->getGlobalProcessId()
            );
        } catch (QUI\Exception) {
        }

        if ($Order === null && $Transaction->getHash() !== '') {
            try {
                $Order = Handler::getInstance()->getOrderByHash(
                    $Transaction->getHash()
                );
            } catch (QUI\Exception) {
            }

            if ($Order === null) {
                try {
                    $Order = Handler::getInstance()->getOrderInProcessByHash(
                        $Transaction->getHash()
                    );
                } catch (QUI\Exception) {
                }
            }
        }

        if ($Order === null) {
            return;
        }

        try {
            $Order->setAttribute('paid_status', QUI\ERP\Constants::PAYMENT_STATUS_OPEN);
            $Order->calculatePayments();
        } catch (Exception $Exception) {
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
    ): void {
        if (!class_exists('QUI\ERP\Accounting\Invoice\Invoice')) {
            return;
        }

        try {
            $Process = new QUI\ERP\Process($Invoice->getGlobalProcessId());
            $Order = $Process->getOrder();

            if (!$Order) {
                return;
            }

            if ($Order->hasInvoice() && $Order->getInvoice() instanceof QUI\ERP\Accounting\Invoice\Invoice) {
                return;
            }

            QUI::getDataBase()->update(
                Handler::getInstance()->table(),
                ['invoice_id' => $Invoice->getUUID()],
                ['hash' => $Order->getUUID()]
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    /**
     * event: order creation
     *
     * @param Order $Order
     */
    public static function onQuiqqerOrderCreated(Order $Order): void
    {
        CustomerData::parseSessionOrderCommentsToOrder($Order);

        if (Settings::getInstance()->get('order', 'sendAdminOrderConfirmation')) {
            try {
                Mail::sendAdminOrderConfirmationMail($Order);
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        if (Settings::getInstance()->get('order', 'sendOrderConfirmation')) {
            try {
                Mail::sendOrderConfirmationMail($Order);
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }
    }

    /**
     * @param QUI\Package\Package $Package
     * @throws QUI\Exception
     */
    public static function onPackageSetup(QUI\Package\Package $Package): void
    {
        if ($Package->getName() !== 'quiqqer/order') {
            return;
        }

        // create order status
        $Handler = ProcessingStatus\Handler::getInstance();
        $Factory = ProcessingStatus\Factory::getInstance();
        $list = $Handler->getList();

        // (Re-)create translations for status change notification
        foreach ([1, 2, 3, 4, 5] as $statusId) {
            $Handler->createNotificationTranslations($statusId);
        }

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

    /**
     * @param Collector $Collection
     * @param QUI\ERP\Products\Product\Types\AbstractType $Product
     * @throws Exception
     */
    public static function onDetailEquipmentButtons(
        Collector $Collection,
        QUI\ERP\Products\Product\Types\AbstractType $Product
    ): void {
        // add to basket -> only for complete products
        // variant products cant be added directly
        if (
            $Product instanceof QUI\ERP\Products\Product\Product
            || $Product instanceof QUI\ERP\Products\Product\Types\VariantChild
        ) {
            /* @var $Product QUI\ERP\Products\Product\Product */
            $AddToBasket = new QUI\ERP\Order\Controls\Buttons\ProductToBasket([
                'Product' => $Product,
                'input' => false
            ]);

            $Collection->append(
                $AddToBasket->create()
            );

            return;
        }

        try {
            $url = $Product->getUrl();

            $Collection->append(
                '<a href="' . $url . '"><span class="fa fa-chevron-right"></span></a>'
            );
        } catch (QUI\Exception) {
        }
    }

    /**
     * @param QUI\Template $Template
     */
    public static function onTemplateGetHeader(QUI\Template $Template): void
    {
        $merge = 0;

        try {
            $Package = QUI::getPackage('quiqqer/order');
            $Config = $Package->getConfig();
            $merge = $Config->getValue('orderProcess', 'mergeSameProducts') ? 1 : 0;
        } catch (QUI\Exception) {
        }

        $header = '<script type="text/javascript">';
        $header .= 'window.QUIQQER_ORDER_ORDER_PROCESS_MERGE = ' . $merge . ';';
        $header .= '</script>';
        $Template->extendHeader($header);


        $Template->extendFooter(
            '<script>
                (function() {
                    if (window.location.hash === "#checkout") { 
                        require(["package/quiqqer/order/bin/frontend/controls/orderProcess/Window"], function(Window) { 
                            new Window().open();
                        });
                    }
                })();
            </script>'
        );
    }

    public static function onTemplateEnd(
        Collector $Collection,
        QUI\Template $Template
    ): void {
        $Collection->append(
            '<script src="' . URL_OPT_DIR . 'quiqqer/order/bin/frontend/dataLayerTracking.js"></script>'
        );
    }

    /**
     * quiqqer/order: onQuiqqerOrderProcessStatusChange
     *
     * Sends notifications if an order status is changed (automatically)
     *
     * @param AbstractOrder $Order
     * @param ProcessingStatus\Status $Status
     */
    public static function onQuiqqerOrderProcessStatusChange(
        AbstractOrder $Order,
        ProcessingStatus\Status $Status
    ): void {
        // Do not send auto-notification if the Order was manually saved (backend)
        if ($Order->getAttribute('userSave')) {
            return;
        }

        if (!$Status->isAutoNotification()) {
            return;
        }

        try {
            QUI\ERP\Order\ProcessingStatus\Handler::getInstance()->sendStatusChangeNotification(
                $Order,
                $Status->getId()
            );
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * @param QUI\Users\User $User
     * @param QUI\ERP\Comments $Comments
     */
    public static function onQuiqqerErpGetCommentsByUser(
        QUI\Users\User $User,
        QUI\ERP\Comments $Comments
    ): void {
        $Handler = Handler::getInstance();
        $orders = $Handler->getOrdersByUser($User);

        foreach ($orders as $Order) {
            $Comments->import($Order->getComments());

            // created invoice
            $Comments->addComment(
                QUI::getLocale()->get('quiqqer/order', 'erp.comment.order.created', [
                    'orderId' => $Order->getUUID()
                ]),
                strtotime($Order->getAttribute('c_date'))
            );
        }
    }

    public static function onQuiqqerSalesOrdersSaveEnd(QUI\ERP\SalesOrders\SalesOrder $SalesOrder): void
    {
        $orderId = $SalesOrder->getData('orderId');

        if (empty($orderId)) {
            return;
        }

        try {
            $Order = QUI\ERP\Order\Handler::getInstance()->get($orderId);
        } catch (QUI\Exception) {
            return;
        }

        // sales order shipping
        $shippingTracking = $Order->getDataEntry('shippingTracking');
        $soShippingTracking = $SalesOrder->getData('shippingTracking');

        if (is_string($soShippingTracking)) {
            $soShippingTracking = json_decode($soShippingTracking, true);
        }

        if (is_string($shippingTracking)) {
            $shippingTracking = json_decode($shippingTracking, true);
        }

        try {
            // set shipping tracking
            if (
                is_array($soShippingTracking)
                && is_array($shippingTracking)
                && isset($soShippingTracking['number'])
                && isset($shippingTracking['number'])
                && isset($soShippingTracking['type'])
                && isset($shippingTracking['type'])
                && ($soShippingTracking['number'] !== $shippingTracking['number']
                    || $soShippingTracking['type'] !== $shippingTracking['type'])
            ) {
                $Order->setData('shippingTracking', $soShippingTracking);
            }

            // set shipping
            if ($SalesOrder->getShipping()) {
                $Order->setShipping($SalesOrder->getShipping());
            }

            $Order->update(QUI::getUsers()->getSystemUser());

            if ($SalesOrder->getShippingStatus()) {
                $Order->setShippingStatus($SalesOrder->getShippingStatus()); // triggers update :-/
            }
        } catch (QUI\Exception) {
        }
    }

    /**
     * @throws QUI\Database\Exception
     */
    public static function onQuiqqerMigrationV2(QUI\System\Console\Tools\MigrationV2 $Console): void
    {
        $Console->writeLn('- Migrate orders');

        // check invoice id field
        $orderTable = Handler::getInstance()->table();
        $orderProcessTable = Handler::getInstance()->tableOrderProcess();

        QUI::getDatabase()->execSQL(
            'ALTER TABLE `' . $orderTable . '` CHANGE `invoice_id` `invoice_id` VARCHAR(50) NULL DEFAULT NULL;'
        );

        QUI::getDatabase()->execSQL(
            'ALTER TABLE `' . $orderTable . '` CHANGE `customerId` `customerId` VARCHAR(50) NULL DEFAULT NULL;'
        );

        QUI::getDatabase()->execSQL(
            'ALTER TABLE `' . $orderProcessTable . '` CHANGE `invoice_id` `invoice_id` VARCHAR(50) NULL DEFAULT NULL;'
        );

        QUI::getDatabase()->execSQL(
            'ALTER TABLE `' . $orderProcessTable . '` CHANGE `customerId` `customerId` VARCHAR(50) NULL DEFAULT NULL;'
        );


        QUI\Utils\MigrationV1ToV2::migrateUsers($orderTable, [
            'customerId',
            'c_user'
        ]);

        // migrate invoice ids
        // @todo kontrollieren
        $result = QUI::getDataBase()->fetch([
            'from' => $orderTable
        ]);

        foreach ($result as $order) {
            if (!is_numeric($order['invoice_id'])) {
                continue;
            }

            try {
                $Invoice = QUI\ERP\Accounting\Invoice\Handler::getInstance()->getInvoice($order['invoice_id']);

                QUI::getDataBase()->update(
                    $orderTable,
                    ['invoice_id' => $Invoice->getUUID()],
                    ['id' => $order['id']]
                );
            } catch (QUI\Exception) {
            }
        }
    }
}
