<?php

/**
 * This file contains QUI\ERP\Order\ErpProvider
 */

namespace QUI\ERP\Order;

use QUI\ERP\Api\AbstractErpProvider;

use QUI\Controls\Sitemap\Map;
use QUI\Controls\Sitemap\Item;

/**
 * Class ErpProvider
 *
 * @package QUI\ERP\Order
 */
class ErpProvider extends AbstractErpProvider
{
    /**
     * @param \QUI\Controls\Sitemap\Map $Map
     */
    public static function addMenuItems(Map $Map)
    {
        $Accounting = $Map->getChildrenByName('accounting');

        if ($Accounting === null) {
            $Accounting = new Item([
                'icon'     => 'fa fa-book',
                'name'     => 'accounting',
                'text'     => ['quiqqer/order', 'erp.panel.accounting.text'],
                'opened'   => true,
                'priority' => 1
            ]);

            $Map->appendChild($Accounting);
        }

        $Order = new Item([
            'icon'   => 'fa fa-shopping-basket',
            'name'   => 'order',
            'text'   => ['quiqqer/order', 'erp.panel.order.text'],
            'opened' => true
        ]);

        $Order->appendChild(
            new Item([
                'icon'    => 'fa fa-plus',
                'name'    => 'invoice-create',
                'text'    => ['quiqqer/order', 'erp.panel.order.create.text'],
                'require' => 'package/quiqqer/order/bin/backend/utils/ErpMenuOrderCreate'
            ])
        );

        $Order->appendChild(
            new Item([
                'icon'    => 'fa fa-shopping-basket',
                'name'    => 'invoice-drafts',
                'text'    => ['quiqqer/order', 'erp.panel.order.list.text'],
                'require' => 'package/quiqqer/order/bin/backend/controls/panels/Orders'
            ])
        );

        $Accounting->appendChild($Order);
    }

    /**
     * @return array
     */
    public static function getNumberRanges()
    {
        return [
            new NumberRanges\Order()
        ];
    }
}
