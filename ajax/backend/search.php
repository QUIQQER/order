<?php

/**
 * This file contains package_quiqqer_order_ajax_backend_list
 */

use QUI\ERP\Order\Search;

/**
 * Returns order list for a grid
 *
 * @param string $params - JSON query params
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_backend_search',
    function ($params, $filter) {
        $Search = Search::getInstance();
        $Grid   = new QUI\Utils\Grid();


        // filter
        $filter = json_decode($filter);

        foreach ($filter as $entry => $value) {
            $Search->setFilter($entry, $value);
        }

        // query params
        $query = $Grid->parseDBParams(json_decode($params, true));

        if (isset($query['limit'])) {
            $limit = explode(',', $query['limit']);

            $Search->limit($limit[0], $limit[1]);
        }

        return $Search->searchForGrid();
    },
    array('params', 'filter'),
    'Permission::checkAdminUser'
);
