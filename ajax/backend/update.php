<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_update
 */

/**
 * Update an order
 *
 * @param string|integer $orderId - ID of the order
 * @param string $data - JSON query data
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_update',
    function ($orderId, $data) {
        $Order = QUI\ERP\Order\Handler::getInstance()->get($orderId);
        $data  = json_decode($data, true);

        if (isset($data['customer'])) {
            $Order->setCustomer($data['customer']);
        }

        if (isset($data['addressInvoice'])) {
            $Order->setInvoiceAddress($data['addressInvoice']);
        }

        if (isset($data['addressDelivery'])) {
            $Order->setDeliveryAddress($data['addressInvoice']);
        }

        if (isset($data['articles'])) {
            foreach ($data['articles'] as $article) {
                try {
                    $Order->addArticle(
                        new QUI\ERP\Accounting\Article($article)
                    );
                } catch (QUI\Exception $Exception) {
                }
            }
        }

        \QUI\System\Log::writeRecursive($data);


        $Order->update();
    },
    array('orderId', 'data'),
    'Permission::checkAdminUser'
);
