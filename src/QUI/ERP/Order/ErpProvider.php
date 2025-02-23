<?php

/**
 * This file contains QUI\ERP\Order\ErpProvider
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\Controls\Sitemap\Item;
use QUI\Controls\Sitemap\Map;
use QUI\ERP\Api\AbstractErpProvider;

/**
 * Class ErpProvider
 *
 * @package QUI\ERP\Order
 */
class ErpProvider extends AbstractErpProvider
{
    /**
     * @param Map $Map
     */
    public static function addMenuItems(Map $Map): void
    {
        $Accounting = $Map->getChildrenByName('accounting');

        if ($Accounting === null) {
            $Accounting = new Item([
                'icon' => 'fa fa-book',
                'name' => 'accounting',
                'text' => ['quiqqer/order', 'erp.panel.accounting.text'],
                'opened' => true,
                'priority' => 1
            ]);

            $Map->appendChild($Accounting);
        }

        $Order = new Item([
            'icon' => 'fa fa-shopping-basket',
            'name' => 'order',
            'text' => ['quiqqer/order', 'erp.panel.order.text'],
            'opened' => true,
            'priority' => 1
        ]);

        $Order->appendChild(
            new Item([
                'icon' => 'fa fa-plus',
                'name' => 'invoice-create',
                'text' => ['quiqqer/order', 'erp.panel.order.create.text'],
                'require' => 'package/quiqqer/order/bin/backend/utils/ErpMenuOrderCreate'
            ])
        );

        $Order->appendChild(
            new Item([
                'icon' => 'fa fa-shopping-basket',
                'name' => 'invoice-drafts',
                'text' => ['quiqqer/order', 'erp.panel.order.list.text'],
                'require' => 'package/quiqqer/order/bin/backend/controls/panels/Orders'
            ])
        );

        $Accounting->appendChild($Order);
    }

    /**
     * @return array
     */
    public static function getNumberRanges(): array
    {
        return [
            new NumberRanges\Order()
        ];
    }

    /**
     * @return array[]
     */
    public static function getMailLocale(): array
    {
        return [
            [
                'title' => QUI::getLocale()->get('quiqqer/order', 'mail.text.orderConfirmation.title'),
                'description' => QUI::getLocale()->get('quiqqer/order', 'mail.text.orderConfirmation.description'),
                'subject' => ['quiqqer/order', 'order.confirmation.subject'],
                'content' => ['quiqqer/order', 'order.confirmation.body'],

                'subject.description' => ['quiqqer/order', 'order.confirmation.subject.description'],
                'content.description' => ['quiqqer/order', 'order.confirmation.body.description']
            ],
            [
                'title' => QUI::getLocale()->get('quiqqer/order', 'mail.text.orderPaymentSuccess.title'),
                'description' => QUI::getLocale()->get('quiqqer/order', 'mail.text.orderPaymentSuccess.description'),
                'subject' => ['quiqqer/order', 'mail.payment_success.body'],
                'content' => ['quiqqer/order', 'mail.payment_success.body'],

                'subject.description' => ['quiqqer/order', 'mail.description.orderPaymentSuccess.subject'],
                'content.description' => ['quiqqer/order', 'mail.description.orderPaymentSuccess.body']
            ],
            [
                'title' => QUI::getLocale()->get('quiqqer/order', 'shipping.confirmation.title'),
                'description' => QUI::getLocale()->get('quiqqer/order', 'shipping.confirmation.description'),
                'subject' => ['quiqqer/order', 'shipping.order.mail.subject'],
                'content' => ['quiqqer/order', 'shipping.order.mail.body'],

                'subject.description' => ['quiqqer/order', 'mail.description.shipping.confirmation.subject'],
                'content.description' => ['quiqqer/order', 'mail.description.shipping.confirmation.body']
            ]
        ];
    }
}
