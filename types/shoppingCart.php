<?php

use QUI\ERP\Order\Controls;

try {
    $BasketControl = null;
    $checkoutUrl   = false;
    $Registration  = null;
    $Login         = null;

    $User = QUI::getUserBySession();

    // guest
    if (!QUI::getUsers()->isUser($User) || $User->getType() == QUI\Users\Nobody::class) {
        $Registration = new Controls\Checkout\Registration();
        $Login        = new Controls\Checkout\Login();
    } else {
        // user is logged
        $Orders = QUI\ERP\Order\Handler::getInstance();
        $Basket = $Orders->getBasketFromUser($User);

        $BasketControl = new QUI\ERP\Order\Controls\OrderProcess\Basket([
            'basketId' => $Basket->getId()
        ]);

        try {
            $OrderProcessSite = QUI\ERP\Order\Utils\Utils::getOrderProcess($Project);
        } catch (QUI\Exception $Exception) {
            $OrderProcessSite = $Project->firstChild();
        }

        $checkoutUrl = $OrderProcessSite->getUrlRewritten();
    }

    $Engine->assign([
        'Basket'       => $BasketControl,
        'checkoutUrl'  => $checkoutUrl,
        'Registration' => $Registration,
        'Login'        => $Login
    ]);
} catch (QUI\DataBase\Exception $Exception) {
    $ExceptionReplacement = new QUI\Exception(['quiqqer/quiqqer', 'exception.error']);

    QUI\System\Log::writeException($Exception);

    $Engine->assign([
        'Exception' => $ExceptionReplacement
    ]);
} catch (Exception $Exception) {
    $Engine->assign([
        'Exception' => $Exception
    ]);
}
