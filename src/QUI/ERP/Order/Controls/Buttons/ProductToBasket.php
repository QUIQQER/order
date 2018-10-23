<?php

/**
 * This file contains QUI\ERP\Order\Controls\Buttons\ProductToBasket
 */

namespace QUI\ERP\Order\Controls\Buttons;

use QUI;

/**
 * Class ProductToBasket
 *
 * @package QUI\ERP\Order\Controls\Buttons
 */
class ProductToBasket extends QUI\Control
{
    /**
     * constructor
     *
     * @param array $attributes
     */
    public function __construct($attributes = array())
    {
        $this->setAttributes(array(
            'nodeName' => 'div',
            'data-qui' => 'package/quiqqer/order/bin/frontend/controls/buttons/ProductToBasket',
            'input'    => true,
            'Product'  => false
        ));

        parent::__construct($attributes);

        $this->addCSSClass('quiqqer-order-button-add');
        $this->addCSSClass('button--callToAction');
        $this->addCSSClass('button');
        $this->addCSSClass('disabled');
        $this->addCSSFile(dirname(__FILE__).'/ProductToBasket.css');
    }

    /**
     * (non-PHPdoc)
     *
     * @see \QUI\Control::create()
     */
    public function getBody()
    {
        $Engine  = QUI::getTemplateManager()->getEngine();

        if ($this->getAttribute('Product')) {
            /* @var $Product QUI\ERP\Products\Product\Product */
            $Product = $this->getAttribute('Product');

            $this->setAttribute('data-pid', $Product->getId());

            $this->setAttribute('title', QUI::getLocale()->get(
                'quiqqer/order',
                'control.order.buttonAdd.title',
                array(
                    'productId' => $Product->getId(),
                    'product'   => $Product->getTitle(),
                )
            ));
        }

        $Engine->assign([
            'this' => $this
        ]);
        
        return $Engine->fetch(dirname(__FILE__) . '/ProductToBasket.html');
    }
}
