<?php

/**
 * This file contains QUI\ERP\Order\Controls\Checkout\Registration
 */

namespace QUI\ERP\Order\Controls\Checkout;

use QUI;

/**
 * Class Registration
 *
 * @package QUI\ERP\Order\Controls\Checkout
 */
class Registration extends QUI\Control
{
    /**
     * Registration constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        $this->setAttributes([
            'autofill' => true
        ]);

        parent::__construct($attributes);
    }

    /**
     * @return string
     */
    public function getBody()
    {
        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            return '';
        }

        $this->addCSSFile(dirname(__FILE__).'/Registration.css');

        $Registration = new QUI\FrontendUsers\Controls\RegistrationSignUp([
            'content'  => false,
            'autofill' => $this->getAttribute('autofill')
        ]);

        $Engine->assign([
            'Registration' => $Registration
        ]);

        return $Engine->fetch(dirname(__FILE__).'/Registration.html');
    }
}
