<?php

$Site->setAttribute('nocache', true);

try {
    $OrderProcess = new QUI\ERP\Order\OrderProcess([
        'step'           => $Site->getAttribute('order::step'),
        'orderHash'      => $Site->getAttribute('order::hash'),
        'basketEditable' => true
    ]);

    $Engine->assign([
        'OrderProcess' => $OrderProcess
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
