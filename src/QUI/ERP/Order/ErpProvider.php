<?php

/**
 * This file contains QUI\ERP\Order\ErpProvider
 */

namespace QUI\ERP\Order;

use QUI\ERP\Api\AbstractErpProvider;

/**
 * Class ErpProvider
 *
 * @package QUI\ERP\Order
 */
class ErpProvider extends AbstractErpProvider
{
    /**
     * @return array
     */
    public static function getMenuItems()
    {
        $menu = array();

        $menu[] = array(
            'icon'  => 'fa fa-shopping-cart',
            'text'  => array('quiqqer/order', 'erp.panel.order.text'),
            'panel' => 'package/quiqqer/order/bin/backend/controls/panels/Orders'
        );

        return $menu;
    }
}
