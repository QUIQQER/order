<?php

namespace QUI\ERP\Order\Rest;

/**
 * Specific fields in REST API responses.
 */
enum ResponseField: string
{
    case MSG = 'msg';
    case ERROR = 'error';
    case ERROR_CODE = 'error_code';
    case ERROR_DESCRIPTION = 'error_description';
    case SUCCESS = 'success';
    case ORDER_UUIDS = 'orderUuids';
    case ORDERS = 'orders';
}
