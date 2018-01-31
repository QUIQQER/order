<?php

/**
 * This file contains QUI\ERP\Order\Utils\Utils
 */

namespace QUI\ERP\Order\Utils;

use QUI;

/**
 * Class Utils
 *
 * @package QUI\ERP\Order\Utils
 */
class Utils
{
    /**
     * Return the url to the checkout / order process
     *
     * @param QUI\Projects\Project $Project
     * @return QUI\Projects\Site
     *
     * @throws QUI\ERP\Order\Exception
     */
    public static function getOrderProcess(QUI\Projects\Project $Project)
    {
        $sites = $Project->getSites(array(
            'where' => array(
                'type' => 'quiqqer/order:types/orderingProcess'
            ),
            'limit' => 1
        ));

        if (isset($sites[0])) {
            return $sites[0];
        }

        throw new QUI\ERP\Order\Exception(array(
            'quiqqer/order',
            'exception.order.process.not.found'
        ));
    }

    /**
     * @param QUI\Projects\Project $Project
     * @return QUI\Projects\Site
     *
     * @throws QUI\ERP\Order\Exception
     */
    public static function getCheckout(QUI\Projects\Project $Project)
    {
        return self::getOrderProcess($Project);
    }
}
