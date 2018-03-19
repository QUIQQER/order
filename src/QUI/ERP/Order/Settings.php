<?php

/**
 * This file contains QUI\ERP\Order\Settings
 */

namespace QUI\ERP\Order;

use QUI;

/**
 * Class Settings
 * - Main helper to ask for settings
 *
 * @package QUI\ERP\Order
 */
class Settings extends QUI\Utils\Singleton
{
    /**
     * @var array
     */
    protected $settings = [];

    /**
     * Settings constructor.
     */
    public function __construct()
    {
        try {
            $Config = QUI::getPackage('quiqqer/order')->getConfig();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return;
        }

        $this->settings = $Config->toArray();
    }

    /**
     * Return the setting
     *
     * @param string $section
     * @param string $key
     *
     * @return mixed
     */
    public function get($section, $key)
    {
        if (isset($this->settings[$section][$key])) {
            return $this->settings[$section][$key];
        }

        return false;
    }

    /**
     * Return the setting
     *
     * @param string $section
     * @param string $key
     * @param string|bool|integer|float $value
     */
    public function set($section, $key, $value)
    {
        $this->settings[$section][$key] = $value;
    }

    //region special settings

    //endregion
}
