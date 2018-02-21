<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_address_save
 */

/**
 * Save the address data to the address
 *
 * @param integer $addressId - Address ID
 * @param string $data - JSON data
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_address_save',
    function ($addressId, $data) {

    },
    array('addressId', $data)
);
