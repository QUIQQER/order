<?php

/**
 * This file contains QUI\ERP\Order\ProcessingStatus\StatusUnknown
 */

namespace QUI\ERP\Order\ProcessingStatus;

use QUI;
use QUI\ERP\Order\AbstractOrder;

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
    protected $id = 0;

    /**
     * @var string
     */
    protected $color = '#999';

    /**
     * @var bool
     */
    protected $notification = false;

    /**
     * Status constructor
     */
    public function __construct()
    {
    }

    /**
     * Return the title
     *
     * @param null|QUI\Locale (optional) $Locale
     * @return string
     */
    public function getTitle($Locale = null)
    {
        if (!($Locale instanceof QUI\Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/order', 'processing.status.unknown');
    }
}
