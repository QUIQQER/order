<?php

/**
 * This file contains QUI\ERP\Order\Utils\Utils
 */

namespace QUI\ERP\Order\Utils;

use QUI;

/**
 * Class Utils
 * Helper to get some stuff (urls and information's) easier for the the order
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
        $sites = $Project->getSites([
            'where' => [
                'type' => 'quiqqer/order:types/orderingProcess'
            ],
            'limit' => 1
        ]);

        if (isset($sites[0])) {
            return $sites[0];
        }

        throw new QUI\ERP\Order\Exception([
            'quiqqer/order',
            'exception.order.process.not.found'
        ]);
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
     * @param null|QUI\ERP\Order\Controls\AbstractOrderingStep $Step
     * @return string
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     */
    public static function getOrderProcessUrl(QUI\Projects\Project $Project, $Step = null)
    {
        if (self::$url === null) {
            self::$url = self::getOrderProcess($Project)->getUrlRewritten();
        }

        if ($Step instanceof QUI\ERP\Order\Controls\AbstractOrderingStep) {
            $url = self::$url;
            $url = $url.'/'.$Step->getName();

            return $url;
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
     * @param QUI\Projects\Project $Project
     * @param QUI\ERP\Order\OrderInterface $Order
     *
     * @return string
     */
    public static function getOrderProfileUrl(QUI\Projects\Project $Project, $Order)
    {
        if (!($Order instanceof QUI\ERP\Order\Order) &&
            !($Order instanceof QUI\ERP\Order\OrderView) &&
            !($Order instanceof QUI\ERP\Order\OrderInProcess)) {
            return '';
        }

        $sites = $Project->getSites([
            'where' => [
                'type' => 'quiqqer/frontend-users:types/profile'
            ],
            'limit' => 1
        ]);

        if (!isset($sites[0])) {
            return '';
        }

        /* @var $Site QUI\Projects\Site */
        $Site = $sites[0];

        try {
            $url = $Site->getUrlRewritten();
        } catch (QUI\Exception $Exception) {
            return '';
        }

        $ending = false;

        if (strpos($url, '.html')) {
            $url    = str_replace('.html', '', $url);
            $ending = true;
        }

        // parse the frontend users category
        $url .= '/erp/erp-order#'.$Order->getHash();

        if ($ending) {
            $url .= '.html';
        }

        return $url;
    }

    /**
     * Return the order prefix for every order / order in process
     *
     * @return string
     */
    public static function getOrderPrefix()
    {
        try {
            $Package = QUI::getPackage('quiqqer/order');
            $Config  = $Package->getConfig();
            $setting = $Config->getValue('order', 'prefix');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return date('Y').'-';
        }

        if ($setting === false) {
            return date('Y').'-';
        }

        $prefix = strftime($setting);

        if (mb_strlen($prefix) < 100) {
            return $prefix;
        }

        return mb_substr($prefix, 0, 100);
    }

    /**
     * Can another payment method be chosen if the payment method does not work in an order?
     *
     * @param QUI\ERP\Accounting\Payments\Types\Payment $Payment
     * @return bool
     */
    public static function isPaymentChangeable(QUI\ERP\Accounting\Payments\Types\Payment $Payment)
    {
        $Settings = QUI\ERP\Order\Settings::getInstance();

        return (bool)$Settings->get('paymentChangeable', $Payment->getId());
    }
}
