<?php

define('SYSTEM_INTERN', true);
define('QUIQQER_SYSTEM', true);

require dirname(dirname(dirname(dirname(__FILE__)))) . '/header.php';

// Order
try {
    if (empty($argv[1])) {
        echo "\nEXIT: No OrderInProcess ID provied.\n\n";
        exit;
    }

    $orderInProcessId = (int)$argv[1];

    echo "\nCreating Order out of OrderInProcess #".$orderInProcessId."\n";

    $OrderInProcess = QUI\ERP\Order\Handler::getInstance()->getOrderInProcess($orderInProcessId);
    $OrderInProcess->createOrder(QUI::getUsers()->getSystemUser());

    echo "\nSUCCESS!\n\n";
} catch (\Exception $Exception) {
    \QUI\System\Log::writeRecursive($Exception);
    echo "\nERROR: ".$Exception->getMessage()."\n\n";
}

exit;
