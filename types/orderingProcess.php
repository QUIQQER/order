<?php

try {
    $OrderProcess = new QUI\ERP\Order\OrderProcess(array(
        'step'      => $Site->getAttribute('order::step'),
        'orderHash' => $Site->getAttribute('order::hash')
    ));

    $Engine->assign(array(
        'OrderProcess' => $OrderProcess
    ));
} catch (QUI\DataBase\Exception $Exception) {
    $ExceptionReplacement = new QUI\Exception(['quiqqer/quiqqer', 'exception.error']);

    QUI\System\Log::writeException($Exception);

    $Engine->assign(array(
        'Exception' => $ExceptionReplacement
    ));
} catch (Exception $Exception) {
    $Engine->assign(array(
        'Exception' => $Exception
    ));
}
