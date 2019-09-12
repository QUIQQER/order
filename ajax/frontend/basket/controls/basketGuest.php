<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_basket_existsArticle
 */

/**
 * Is the product still active and available?
 *
 * @param integer $productId
 * @param string $options
 * @return bool
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_basket_controls_basketGuest',
    function ($products, $options = '', $editable = true) {
        if (isset($options) && !\is_array($options)) {
            $options = \json_decode($options, true);

            if (empty($options)) {
                $options = [];
            }
        }

        if ($editable === '') {
            $editable = true;
        }

        $products = \json_decode($products, true);
        $Basket   = new QUI\ERP\Order\Basket\BasketGuest();
        $Basket->import($products);

        $Control = new QUI\ERP\Order\Controls\Basket\Basket([
            'editable' => \boolval($editable)
        ]);
        $Control->setAttributes($options);
        $Control->setBasket($Basket);

        return $Control->create();
    },
    ['products', 'options', 'editable']
);
