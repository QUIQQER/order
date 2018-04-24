<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_processingStatus_list
 */

use QUI\ERP\Order\ProcessingStatus\Handler;

/**
 * Returns processing list for a grid
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_processingStatus_list',
    function () {
        $Grid    = new QUI\Utils\Grid();
        $Handler = Handler::getInstance();

        $list   = $Handler->getProcessingStatusList();
        $result = array_map(function ($Status) {
            /* @var $Status \QUI\ERP\Accounting\Invoice\ProcessingStatus\Status */
            return $Status->toArray(QUI::getLocale());
        }, $list);

        usort($result, function ($a, $b) {
            if ($a['id'] == $b['id']) {
                return 0;
            }

            return $a['id'] > $b['id'] ? 1 : -1;
        });

        return $Grid->parseResult($result, count($result));
    },
    false,
    'Permission::checkAdminUser'
);
