<?php

try {
    $User = QUI::getUserBySession();

    if (!QUI::getUsers()->isUser($User) || $User->getType() == QUI\Users\Nobody::class) {
        $BasketControl = new QUI\ERP\Order\Basket\BasketGuest();

        // @todo für Nobody muss das noch gemacht werden - es geht im Moment nicht.
        $Engine->assign([
            'Basket' => $BasketControl
        ]);

        return;
    }

    $Orders = QUI\ERP\Order\Handler::getInstance();
    $Basket = $Orders->getBasketFromUser($User);

    $BasketControl = new QUI\ERP\Order\Controls\OrderProcess\Basket([
        'basketId' => $Basket->getId()
    ]);

    $Engine->assign([
        'Basket' => $BasketControl
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
