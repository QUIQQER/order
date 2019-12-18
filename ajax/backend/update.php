<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_update
 */

use QUI\ERP\Order\ProcessingStatus\Handler;

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
        $Order = QUI\ERP\Order\Handler::getInstance()->get((int)$orderId);
        $data  = \json_decode($data, true);

        // customer
        $Customer = null;

        if (isset($data['customerId']) && !isset($data['customer'])) {
            $Customer = QUI::getUsers()->get($data['customerId']);
        }

        if (!$Customer && isset($data['customer'])) {
            if (isset($data['customerId'])
                && !isset($data['customer']['id'])
            ) {
                $data['customer']['id'] = (int)$data['customerId'];
            }

            if (isset($data['addressInvoice']['country'])
                && !isset($data['customer']['country'])
            ) {
                $data['customer']['country'] = $data['addressInvoice']['country'];
            }

            if (!isset($data['customer']['username'])) {
                $data['customer']['username'] = '';
            }

            if (!isset($data['customer']['firstname'])
                && isset($data['addressInvoice']['firstname'])
            ) {
                $data['customer']['firstname'] = $data['addressInvoice']['firstname'];
            } elseif (!isset($data['customer']['firstname'])) {
                $data['customer']['firstname'] = '';
            }

            if (!isset($data['customer']['lastname'])
                && isset($data['addressInvoice']['lastname'])
            ) {
                $data['customer']['lastname'] = $data['addressInvoice']['lastname'];
            } elseif (!isset($data['customer']['lastname'])) {
                $data['customer']['lastname'] = '';
            }

            if (!isset($data['customer']['lang'])) {
                $data['customer']['lang'] = QUI::getLocale()->getCurrent();
            }

            if (!isset($data['customer']['isCompany'])) {
                $data['customer']['isCompany'] = false;
            }

            $Customer = new QUI\ERP\User($data['customer']);


            if (isset($data['customer']['quiqqer.erp.taxId'])) {
                $Customer->setAttribute(
                    'quiqqer.erp.taxId',
                    $data['customer']['quiqqer.erp.taxId']
                );
            }

            if (isset($data['customer']['quiqqer.erp.euVatId'])) {
                $Customer->setAttribute(
                    'quiqqer.erp.euVatId',
                    $data['customer']['quiqqer.erp.euVatId']
                );
            }
        }

        if ($Customer) {
            $Order->setCustomer($Customer);
        }


        // addresses
        if (isset($data['addressInvoice'])) {
            $Order->setInvoiceAddress($data['addressInvoice']);
        }


        if (isset($data['addressDelivery']) && !empty($data['addressDelivery'])) {
            $Order->setDeliveryAddress($data['addressInvoice']);
        } elseif (isset($data['addressDelivery']) && empty($data['addressDelivery'])) {
            $Order->removeDeliveryAddress();
        }

        if (isset($data['articles'])) {
            $Order->clearArticles();

            foreach ($data['articles'] as $article) {
                try {
                    $Order->addArticle(
                        new QUI\ERP\Accounting\Article($article)
                    );
                } catch (QUI\Exception $Exception) {
                }
            }
        }

        if (isset($data['paymentId'])) {
            try {
                $Order->setPayment($data['paymentId']);
            } catch (QUI\ERP\Order\Exception $Exception) {
            }
        }

        if (isset($data['status']) && $data['status'] !== false) {
            try {
                $Order->setProcessingStatus($data['status']);

                // Send status notification
                if (!empty($data['notification'])) {
                    Handler::getInstance()->sendStatusChangeNotification(
                        $Order,
                        (int)$data['status'],
                        $data['notification']
                    );
                }
            } catch (QUI\ERP\Order\Exception $Exception) {
                QUI\System\Log::addError($Exception->getMessage());
            }
        }

        if (isset($data['shippingStatus']) && $data['shippingStatus'] !== false) {
            try {
                $Order->setShippingStatus($data['shippingStatus']);

                // Send status notification @todo
                if (!empty($data['notificationShipping'])) {
                }
            } catch (QUI\ERP\Order\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        $Order->setAttribute('userSave', true);
        $Order->update();

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/order',
                'message.backend.update.success',
                [
                    'oderId' => $Order->getId()
                ]
            )
        );
    },
    ['orderId', 'data'],
    'Permission::checkAdminUser'
);
