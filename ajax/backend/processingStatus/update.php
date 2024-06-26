<?php

/**
 * Update a processing status
 *
 * @param int $id - ProcessingStatus ID
 * @param string $color - hex color code
 * @param array $title - (multilingual) title
 * @param bool $notification - send auto-notification on status change
 */

use QUI\ERP\Order\ProcessingStatus\Handler;
use QUI\Utils\Security\Orthos;

QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_processingStatus_update',
    function ($id, $color, $title, $notification) {
        $id = (int)$id;
        $Handler = Handler::getInstance();

        $Handler->updateProcessingStatus(
            $id,
            Orthos::clear($color),
            Orthos::clearArray(json_decode($title, true))
        );

        $Handler->setProcessingStatusNotification($id, boolval($notification));
    },
    ['id', 'color', 'title', 'notification'],
    'Permission::checkAdminUser'
);
