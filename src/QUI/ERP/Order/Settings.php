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
     * @var null|bool
     */
    protected ?bool $isInvoiceInstalled = null;

    /**
     * @var bool
     */
    protected bool $forceCreateInvoice = false;

    /**
     * @var array<string, mixed>
     */
    protected array $settings = [];

    /**
     * Return the required order package configuration.
     *
     * @throws QUI\Exception
     */
    public static function getConfig(): QUI\Config
    {
        $Config = QUI::getPackage('quiqqer/order')->getConfig();

        if ($Config === null) {
            throw new QUI\Exception('Order configuration is not available.');
        }

        return $Config;
    }

    /**
     * Settings constructor.
     *
     * @throws QUI\Exception
     */
    public function __construct()
    {
        $this->settings = self::getConfig()->toArray();
    }

    /**
     * Return the setting
     *
     * @param string $section
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $section, string $key): mixed
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
    public function set(string $section, string $key, mixed $value): void
    {
        $this->settings[$section][$key] = $value;
    }

    /**
     * Is the invoice module installed?
     *
     * @return bool|null
     */
    public function isInvoiceInstalled(): ?bool
    {
        if ($this->isInvoiceInstalled === null) {
            try {
                QUI::getPackage('quiqqer/invoice');
                $this->isInvoiceInstalled = true;
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
                $this->isInvoiceInstalled = false;
            }
        }

        return $this->isInvoiceInstalled;
    }

    //region invoice settings

    /**
     * Create invoice directly on order creation
     *
     * @return bool
     * @throws QUI\Exception
     */
    public function createInvoiceOnOrder(): bool
    {
        if ($this->isInvoiceInstalled() === false) {
            return false;
        }

        $Config = self::getConfig();

        return $Config->get('order', 'autoInvoice') === 'onOrder';
    }

    /**
     * Create invoice if the order is paid
     *
     * @return bool
     * @throws QUI\Exception
     */
    public function createInvoiceOnPaid(): bool
    {
        if ($this->isInvoiceInstalled() === false) {
            return false;
        }


        $Config = self::getConfig();

        if (
            $Config->get('order', 'autoInvoice') === 'onPaid'
            || $Config->get('order', 'autoInvoice') === 'byPayment'
        ) {
            return true;
        }

        return false;
    }

    /**
     * Create invoice if the payment said it
     *
     * @return bool
     * @throws QUI\Exception
     */
    public function createInvoiceByPayment(): bool
    {
        if ($this->isInvoiceInstalled() === false) {
            return false;
        }


        $Config = self::getConfig();

        return $Config->get('order', 'autoInvoice') === 'byPayment';
    }

    /**
     * should invoice creation still be executed even if an invoice already exists?
     *
     * @return bool
     */
    public function forceCreateInvoice(): bool
    {
        if ($this->isInvoiceInstalled() === false) {
            return false;
        }

        return $this->forceCreateInvoice;
    }

    /**
     * Set force create invoice on
     */
    public function forceCreateInvoiceOn(): void
    {
        $this->forceCreateInvoice = true;
    }

    /**
     * Set force create invoice off
     */
    public function forceCreateInvoiceOff(): void
    {
        $this->forceCreateInvoice = false;
    }

    //endregion
}
