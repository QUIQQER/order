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
            'btnClass'     => 'btn btn-primary',
            'btnText'      => false,
            'showLabel'    => true,
            'showControls' => true, // show decrease and increase buttons -/+
            //'disabled'     => false
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

        $Locale      = QUI::getLocale();
        $maxQuantity = '';

        $addButtonTitle = QUI::getLocale()->get(
            'quiqqer/order',
            'control.basket.buttonAdd.text'
        );

        if ($this->getAttribute('Product')) {
            /* @var $Product QUI\ERP\Products\Product\Product */
            $Product     = $this->getAttribute('Product');
            $maxQuantity = $Product->getMaximumQuantity();

            if (!$maxQuantity) {
                $maxQuantity = '';
            }

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

        if ($this->existsAttribute('disabled') && $this->getAttribute('disabled')) {
            $this->setAttribute('data-qui-options-disabled', true);
            $this->addCSSClass('disabled');
        }

        // css class to manage the default browser spin (input from type "number")
        $disableSpins = '';

        if ($this->getAttribute('showControls')) {
            $disableSpins = 'disable-spins';
        }

        // btn text
        $btnText = $Locale->get('quiqqer/order', 'control.basket.buttonAdd.text');

        if ($this->getAttribute('btnText')) {
            $btnText = $this->getAttribute('btnText');
        }

        $Engine->assign([
            'this'           => $this,
            'addButtonTitle' => $addButtonTitle,
            'showLabel'      => $this->getAttribute('showLabel'),
            'showControls'   => $this->getAttribute('showControls'),
            'btnText'        => $btnText,
            'disableSpins'   => $disableSpins,
            'maxQuantity'    => $maxQuantity
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/ProductToBasket.html');
    }
}
