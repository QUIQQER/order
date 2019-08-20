<?php

use QUI\ERP\Order\Controls;

try {
    $BasketControl = null;
    $checkoutUrl   = false;
    $Registration  = null;
    $Login         = null;

    $User = QUI::getUserBySession();

    QUI\System\Log::writeRecursive('1111111111111');
    // guest
    if (!QUI::getUsers()->isUser($User) || $User->getType() == QUI\Users\Nobody::class) {
        QUI\System\Log::writeRecursive('222222222');

        $Registration = new Controls\Checkout\Registration();
        $Login        = new Controls\Checkout\Login();
        QUI\System\Log::writeRecursive('22222222-------------------');

    } else {
        QUI\System\Log::writeRecursive('333333333333333');

        // user is logged
        $Orders = QUI\ERP\Order\Handler::getInstance();
        $Basket = $Orders->getBasketFromUser($User);
        QUI\System\Log::writeRecursive('44444444444');

        $BasketControl = new QUI\ERP\Order\Controls\OrderProcess\Basket([
            'basketId' => $Basket->getId()
        ]);
        QUI\System\Log::writeRecursive('55555555555555');

        try {
            $OrderProcessSite = QUI\ERP\Order\Utils\Utils::getOrderProcess($Project);
        } catch (QUI\Exception $Exception) {
            $OrderProcessSite = $Project->firstChild();
        }

        $checkoutUrl = $OrderProcessSite->getUrlRewritten();
    }
    QUI\System\Log::writeRecursive('aaaaaaaaaaaaaa');

    $Engine->assign([
        'Basket'       => $BasketControl,
        'checkoutUrl'  => $checkoutUrl,
        'Registration' => $Registration,
        'Login'        => $Login
    ]);

    QUI\System\Log::writeRecursive('bbbbbbbbbbbbbbb');

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