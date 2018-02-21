<?php

namespace QUI\ERP\Order\Controls;

use QUI\ERP\Order\Exception;

/**
 * Class OrderingStepInterface
 * @package QUI\ERP\Order\Controls
 */
interface OrderingStepInterface
{
    /**
     * Return the step name
     *
     * @param null|\QUI\Locale $Locale
     * @return string
     */
    public function getName($Locale = null);

    /**
     * @param null|\QUI\Locale $Locale $Locale
     * @return mixed
     */
    public function getTitle($Locale = null);

    /**
     * @throws Exception
     */
    public function validate();

    /**
     * @return bool
     */
    public function isValid();

    /**
     * Save the values from the step into the processing order
     *
     * @return mixed
     * @throws \QUI\ERP\Order\Exception
     */
    public function save();
}
