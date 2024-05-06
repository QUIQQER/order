<?php

/**
 * Create a new  processing status
 *
 * @param int $id - ProcessingStatus ID
 * @param string $color - hex color code
 * @param array $title - (multilignual) titel
 * @param bool $notification - send auto-notification on status change
 */

use QUI\ERP\Order\ProcessingStatus\Factory;
use QUI\ERP\Order\ProcessingStatus\Handler;
use QUI\Utils\Security\Orthos;

QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_processingStatus_create',
    function ($id, $color, $title, $notification) {
        $id = (int)$id;

        Factory::getInstance()->createProcessingStatus(
            $id,
            Orthos::clear($color),
            Orthos::clearArray(json_decode($title, true))
        );

        Handler::getInstance()->setProcessingStatusNotification($id, boolval($notification));
    },
    ['id', 'color', 'title', 'notification'],
    'Permission::checkAdminUser'
);
