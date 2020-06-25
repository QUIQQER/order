<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_isLoggedIn
 */

/**
 * Is the user logged in?
 *
 * @param integer $orderHash
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_isLoggedIn',
    function () {
        return !QUI::getUsers()->isNobodyUser(QUI::getUserBySession());
    }
);
