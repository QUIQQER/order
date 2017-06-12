<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_list
 */

/**
 * Returns order list for a grid
 *
 * @param string $params - JSON query params
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_list',
    function ($params) {
//        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
//        $Grid     = new QUI\Utils\Grid();
//
//        $data = $Invoices->search(
//            $Grid->parseDBParams(json_decode($params, true))
//        );
//
//        return $Grid->parseResult($data, $Invoices->count());
        return array(
            'data'  => array(),
            'page'  => 1,
            'total' => 0
        );
    },
    array('params'),
    'Permission::checkAdminUser'
);
