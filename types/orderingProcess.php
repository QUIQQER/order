<?php

/**
 * This file contains the ordering process site type
 */

/** @var QUI\Projects\Project $Project */
/** @var QUI\Projects\Site $Site */
/** @var QUI\Interfaces\Template\EngineInterface $Engine */

$Site->setAttribute('nocache', true);

try {
    $OrderProcess = new QUI\ERP\Order\OrderProcess([
        'step' => $Site->getAttribute('order::step'),
        'orderHash' => $Site->getAttribute('order::hash'),
        'basketEditable' => true
    ]);

    // setting: one page oder step by step checkout
    // beim setup fragen was verwendet werden soll
    $checkoutType = $Site->getAttribute('quiqqer.order.checkoutType');
    $SimpleCheckout = null;

    if (
        class_exists('QUI\ERP\Order\SimpleCheckout\Checkout')
        && $checkoutType === 'one-page'
        && !$Site->getAttribute('order::hash')
    ) {
        // simple checkout can be used
        $SimpleCheckout = new QUI\ERP\Order\SimpleCheckout\Checkout([
            'data-qui-load-hash-from-url' => 1
        ]);
    }

    $Engine->assign([
        'OrderProcess' => $OrderProcess,
        'SimpleCheckout' => $SimpleCheckout
    ]);
} catch (QUI\Database\Exception $Exception) {
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
