<?php

namespace QUI\ERP\Order\Rest;

/**
 * Specific error codes for quiqqer/order REST API.
 */
enum ErrorCode: int
{
    case MISSING_FIELD = 4001;
    case FIELD_VALUE_INVALID = 4002;
    case ORDER_UUID_ALREADY_EXISTS = 4003;
    case SERVER_ERROR = 5000;
}
