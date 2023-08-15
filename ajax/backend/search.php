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
        $Grid = new QUI\Utils\Grid();
        $params = \json_decode($params, true);

        if (isset($params['sortOn']) && $params['sortOn'] === 'prefixed-id') {
            $params['sortOn'] = 'id';
        }

        // filter
        $filter = \json_decode($filter);

        foreach ($filter as $entry => $value) {
            $Search->setFilter($entry, $value);
        }

        // query params
        $query = $Grid->parseDBParams($params);

        if (isset($query['limit'])) {
            $limit = \explode(',', $query['limit']);

            $Search->limit($limit[0], $limit[1]);
        }

        if (isset($query['order'])) {
            $Search->order($query['order']);
        }

        return $Search->searchForGrid();
    },
    ['params', 'filter'],
    'Permission::checkAdminUser'
);
