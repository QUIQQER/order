<?php

/**
 * This file contains QUI\ERP\Order\Controls\Checkout\Login
 */

namespace QUI\ERP\Order\Controls\Checkout;

use QUI;

/**
 * Class Registration
 *
 * @package QUI\ERP\Order\Controls\Checkout
 */
class Login extends QUI\Control
{
    /**
     * @return string
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        $this->addCSSFile(dirname(__FILE__) . '/Login.css');

        $Login = new QUI\FrontendUsers\Controls\Login([
            'passwordReset' => false
        ]);

        $Engine->assign([
            'Login' => $Login
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/Login.html');
    }
}
