/**
 * @module package/quiqqer/order/bin/ProcessingStatus
 *
 * Main instance of the processing status handler
 *
 * @require package/quiqqer/order/bin/backend/classes/ProcessingStatus
 */
define('package/quiqqer/order/bin/backend/ProcessingStatus', [
    'package/quiqqer/order/bin/backend/classes/ProcessingStatus'
], function (ProcessingStatus) {
    "use strict";
    return new ProcessingStatus();
});
