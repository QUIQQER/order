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
    public function __construct($attributes = [])
    {
        $this->setAttributes([
            'nodeName'     => 'div',
            'data-qui'     => 'package/quiqqer/order/bin/frontend/controls/buttons/ProductToBasket',
            'input'        => true,
            'Product'      => false,
            'showLabel'    => true,
            'showControls' => true // show decrease and increase buttons -/+
        ]);

        parent::__construct($attributes);

        $this->addCSSClass('quiqqer-order-button-add');
        $this->addCSSClass('button--callToAction');
        $this->addCSSClass('button');
        $this->addCSSClass('disabled');
        $this->addCSSFile(\dirname(__FILE__).'/ProductToBasket.css');
    }

    /**
     * (non-PHPdoc)
     *
     * @see \QUI\Control::create()
     */
    public function getBody()
    {
        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            return '';
        }

        $addButtonTitle = QUI::getLocale()->get(
            'quiqqer/order',
            'control.basket.buttonAdd.text'
        );

        if ($this->getAttribute('Product')) {
            /* @var $Product QUI\ERP\Products\Product\Product */
            $Product = $this->getAttribute('Product');

            $this->setAttribute('data-pid', $Product->getId());

            $addButtonTitle = QUI::getLocale()->get(
                'quiqqer/order',
                'control.basket.buttonAdd.title',
                [
                    'productId' => $Product->getId(),
                    'product'   => $Product->getTitle(),
                ]
            );
        }

        // css class to manage the default browser spin (input from type "number")
        $disableSpins = '';
        if ($this->getAttribute('showControls')) {
            $disableSpins = 'disable-spins';
        }

        $Engine->assign([
            'this'           => $this,
            'addButtonTitle' => $addButtonTitle,
            'showLabel'      => $this->getAttribute('showLabel'),
            'showControls'   => $this->getAttribute('showControls'),
            'disableSpins'   => $disableSpins
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/ProductToBasket.html');
    }
}
