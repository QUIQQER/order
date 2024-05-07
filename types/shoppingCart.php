<?php

/**
 * This file contains the shopping card site type
 *
 * @var QUI\Projects\Project $Project
 * @var QUI\Projects\Site $Site
 * @var QUI\Interfaces\Template\EngineInterface $Engine
 **/

use QUI\ERP\Order\Basket\BasketGuest;
use QUI\ERP\Order\Handler;
use QUI\System\Log;

try {
    $BasketControl = null;
    $checkoutUrl = false;
    $Registration = null;
    $Login = null;

    $User = QUI::getUserBySession();
    $Orders = QUI\ERP\Order\Handler::getInstance();

    if (QUI::getUsers()->isNobodyUser($User)) {
        $Basket = new BasketGuest();
    } else {
        $Basket = $Orders->getBasketFromUser($User);
    }

    $BasketControl = new QUI\ERP\Order\Controls\Basket\Basket();
    $BasketControl->setAttribute('isLoading', true);
    $BasketControl->setBasket($Basket);

    try {
        $OrderProcessSite = QUI\ERP\Order\Utils\Utils::getOrderProcess($Project);
    } catch (QUI\Exception $Exception) {
        $OrderProcessSite = $Project->firstChild();
    }

    $checkoutUrl = $OrderProcessSite->getUrlRewritten([], [
        'checkout' => '1'
    ]);

    $Engine->assign([
        'BasketControl' => $BasketControl,
        'checkoutUrl' => $checkoutUrl,
        'Registration' => $Registration,
        'Login' => $Login,
        'Basket' => $Basket
    ]);
} catch (QUI\DataBase\Exception $Exception) {
    $ExceptionReplacement = new QUI\Exception(['quiqqer/core', 'exception.error']);

    QUI\System\Log::writeException($Exception);

    $Engine->assign([
        'Exception' => $ExceptionReplacement
    ]);
} catch (Exception $Exception) {
    $Engine->assign([
        'Exception' => $Exception
    ]);
}
