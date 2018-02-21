<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_address_validateVatId
 */

/**
 * Validate a VAT ID
 *
 * @param integer $taxId
 * @return bool
 * @throws
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_address_validateVatId',
    function ($vatId) {
        QUI\ERP\Tax\Utils::validateVatId($vatId);
        return true;
    },
    array('vatId')
);
