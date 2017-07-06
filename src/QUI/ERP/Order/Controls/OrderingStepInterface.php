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
     * @param null $Locale
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
}
