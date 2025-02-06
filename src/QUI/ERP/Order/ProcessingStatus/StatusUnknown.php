<?php

/**
 * This file contains QUI\ERP\Order\ProcessingStatus\StatusUnknown
 */

namespace QUI\ERP\Order\ProcessingStatus;

use QUI;

/**
 * Class Exception
 *
 * @package QUI\ERP\Order\ProcessingStatus
 */
class StatusUnknown extends Status
{
    /**
     * @var int
     */
    protected int $id = 0;

    /**
     * @var string
     */
    protected mixed $color = '#999';

    /**
     * @var bool
     */
    protected bool $notification = false;

    /**
     * Status constructor
     */
    public function __construct()
    {
    }

    /**
     * Return the title
     *
     * @param null|QUI\Locale $Locale (optional) $Locale
     * @return string
     */
    public function getTitle(null | QUI\Locale $Locale = null): string
    {
        if (!($Locale instanceof QUI\Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/order', 'processing.status.unknown');
    }
}
