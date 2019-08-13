<?php

try {
    $User = QUI::getUserBySession();

    if (!QUI::getUsers()->isUser($User) || $User->getType() == QUI\Users\Nobody::class) {
        // @todo guest ordering
        \header('Location: '.$Project->firstChild()->getUrlRewritten(), false, 301);
        exit;
    }

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

    $Engine->assign([
        'Basket'      => $BasketControl,
        'checkoutUrl' => $OrderProcessSite->getUrlRewritten(),
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
