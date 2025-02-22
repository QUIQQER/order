<?php

namespace QUI\ERP\Order\Controls;

use QUI\ERP\Order\Exception;
use QUI\Locale;

/**
 * Class OrderingStepInterface
 * @package QUI\ERP\Order\Controls
 */
interface OrderingStepInterface
{
    /**
     * Return the step name
     *
     * @param null|Locale $Locale
     * @return string
     */
    public function getName(null | Locale $Locale = null): string;

    /**
     * @param null|Locale $Locale $Locale
     * @return mixed
     */
    public function getTitle(null | Locale $Locale = null): mixed;

    /**
     * @throws Exception
     */
    public function validate(): void;

    /**
     * @return bool
     */
    public function isValid(): bool;

    /**
     * Save the values from the step into the processing order
     *
     * @return void
     * @throws Exception
     */
    public function save(): void;
}
