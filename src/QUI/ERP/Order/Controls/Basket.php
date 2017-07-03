<?php

/**
 * This file contains QUI\ERP\Order\Controls\Basket
 */

namespace QUI\ERP\Order\Controls;

use QUI;

/**
 * Class Basket
 * Coordinates the order process, (order -> payment -> invoice)
 *
 * @package QUI\ERP\Order\Basket
 */
class Basket extends QUI\Control
{
    /**
     * @var QUI\ERP\Order\Basket\Basket
     */
    protected $Basket;

    /**
     * Basket constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = array())
    {
        $orderId = $this->getAttribute('orderId');

        if ($orderId) {
            $this->Basket = new QUI\ERP\Order\Basket\Basket($orderId);
        } else {
            $this->Basket = new QUI\ERP\Order\Basket\Basket();
        }

        parent::__construct($attributes);
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return 'Basket';
    }
}
