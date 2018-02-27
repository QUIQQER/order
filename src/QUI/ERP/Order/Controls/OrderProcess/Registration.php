<?php

/**
 * This file contains QUI\ERP\Order\Controls\Registration
 */

namespace QUI\ERP\Order\Controls\OrderProcess;

use QUI;

/**
 * Class Basket
 * - Basket step
 *
 * @package QUI\ERP\Order\Basket
 */
class Registration extends QUI\ERP\Order\Controls\AbstractOrderingStep
{
    /**
     * Basket constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = array())
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__).'/Registration.css');

        $this->setAttributes([
            'data-qui' => 'package/quiqqer/order/bin/frontend/controls/orderProcess/Registration'
        ]);
    }

    /**
     * @param null $Locale
     * @return string
     */
    public function getName($Locale = null)
    {
        return 'Registration';
    }

    /**
     * @return string
     */
    public function getBody()
    {
        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return '';
        }


        return $Engine->fetch(dirname(__FILE__).'/Registration.html');
    }

    /**
     * @return bool
     */
    public function hasOwnForm()
    {
        return true;
    }

    /**
     *
     */
    public function validate()
    {
        // TODO: Implement validate() method.
    }

    /**
     * @return mixed|void
     */
    public function save()
    {
        // TODO: Implement save() method.
    }
}
