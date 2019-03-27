<?php

/**
 * This file contains QUI\ERP\Order\Controls\Basket
 */

namespace QUI\ERP\Order\Controls\OrderProcess;

use QUI;
use QUI\ERP\Order\Controls\Basket\Basket as BasketControl;

/**
 * Class Basket
 * - Basket step
 *
 * @package QUI\ERP\Order\Basket
 */
class Basket extends QUI\ERP\Order\Controls\AbstractOrderingStep
{
    /**
     * @var QUI\ERP\Order\Basket\Basket
     */
    protected $Basket;

    /**
     * Basket constructor.
     *
     * @param array $attributes
     *
     * @throws QUI\Exception
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        if ($this->getAttribute('Order')) {
            $this->Basket = new QUI\ERP\Order\Basket\BasketOrder(
                $this->getAttribute('Order')->getHash()
            );
        } elseif ($this->getAttribute('Basket')) {
            $this->Basket = $this->getAttribute('Basket');
        } else {
            $this->Basket = new QUI\ERP\Order\Basket\Basket(
                $this->getAttribute('basketId')
            );
        }

        $this->addCSSFile(\dirname(__FILE__).'/Basket.css');
        $this->addCSSClass('quiqqer-order-step-basket');
        $this->setAttribute('nodeName', 'section');
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getName($Locale = null)
    {
        return 'Basket';
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return 'fa fa-shopping-basket';
    }

    /**
     * @return QUI\ERP\Order\Basket\Basket
     */
    public function getBasket()
    {
        return $this->Basket;
    }

    /**
     * @throws QUI\ERP\Order\Exception
     */
    public function validate()
    {
        if (!$this->Basket->count()) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/order',
                'exception.basket.has.no.articles'
            ]);
        }
    }

    /**
     * @return bool
     */
    public function showNext()
    {
        if (!$this->Basket->count()) {
            return false;
        }

        return true;
    }

    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        if (!$this->Basket->count()) {
            return $Engine->fetch(\dirname(__FILE__).'/BasketEmpty.html');
        }

        $BasketControl = new BasketControl();
        $BasketControl->setBasket($this->Basket);

        $Engine->assign([
            'BasketControl' => $BasketControl,
            'Basket'        => $this->Basket
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/Basket.html');
    }

    /**
     * @return mixed|void
     */
    public function save()
    {
    }
}
