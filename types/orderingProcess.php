<?php

try {
    $OrderProcess = new QUI\ERP\Order\OrderProcess(array(
        'step'      => $Site->getAttribute('order::step'),
        'orderHash' => $Site->getAttribute('order::hash')
    ));

    $Engine->assign(array(
        'OrderProcess' => $OrderProcess
    ));
} catch (Exception $Exception) {
    $Engine->assign(array(
        'Exception' => $Exception
    ));
}
