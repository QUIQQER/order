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
     * @var null|string
     */
    protected static $url = null;

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

    /**
     * Return the url to the checkout / order process
     *
     * @param QUI\Projects\Project $Project
     * @return string
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     */
    public static function getOrderProcessUrl(QUI\Projects\Project $Project)
    {
        if (self::$url === null) {
            self::$url = self::getOrderProcess($Project)->getUrlRewritten();
        }

        return self::$url;
    }

    /**
     * @param QUI\Projects\Project $Project
     * @param $hash
     * @return string
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     */
    public static function getOrderProcessUrlForHash(QUI\Projects\Project $Project, $hash)
    {
        $url = self::getOrderProcessUrl($Project);

        return $url.'/Order/'.$hash;
    }

    /**
     * @param QUI\Projects\Project $Project
     * @param QUI\ERP\Order\OrderInterface $Order
     *
     * @return string
     */
    public static function getOrderUrl(QUI\Projects\Project $Project, $Order)
    {
        if (!($Order instanceof QUI\ERP\Order\Order) &&
            !($Order instanceof QUI\ERP\Order\OrderView) &&
            !($Order instanceof QUI\ERP\Order\OrderInProcess)) {
            return '';
        }

        try {
            return self::getOrderProcessUrlForHash($Project, $Order->getHash());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return '';
    }

    /**
     * @return string
     */
    public static function getOrderPrefix()
    {
        try {
            $Package = QUI::getPackage('quiqqer/order');
            $Config  = $Package->getConfig();
            $setting = $Config->getValue('invoice', 'prefix');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return date('Y').'-';
        }

        if ($setting === false) {
            return date('Y').'-';
        }

        return $setting;
    }
}
