<?php

namespace QUI\ERP\Order\Rest;

/**
 * REST API endpoints for quiqqer/order.
 */
enum Endpoint: string
{
    case INSERT = '/order/insert';
    case GET_ORDER_UUIDS_IN_DATE_RANGE = '/order/getOrderUuidsInDateRange';
}
