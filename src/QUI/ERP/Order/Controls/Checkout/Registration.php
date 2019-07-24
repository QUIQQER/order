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
     * @return string
     */
    public function getBody()
    {
        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            return '';
        }

        $Registration = new QUI\FrontendUsers\Controls\RegistrationSignUp([
            'content' => false
        ]);

        $Engine->assign([
            'Registration' => $Registration
        ]);

        return $Engine->fetch(dirname(__FILE__).'/Registration.html');
    }
}
