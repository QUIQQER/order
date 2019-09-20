<?php

/**
 * This file contains QUI\ERP\Order\Controls\Basket
 */

namespace QUI\ERP\Order\Controls\OrderProcess;

use QUI;
use QUI\ERP\Coupons\Handler;
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
        $this->setAttributes([
            'editable' => true
        ]);

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

        /* @var $OrderProcess QUI\ERP\Order\OrderProcess */
        $OrderProcess = $this->getAttribute('OrderProcess');

        // clean up 0 articles
        try {
            if ($OrderProcess) {
                $Current = $OrderProcess->getCurrentStep();

                // if current step is basket, we need no cleanup ... only later
                if ($Current instanceof QUI\ERP\Order\Controls\Basket\Basket) {
                    return;
                }
            }

            $Order    = $this->Basket->getOrder();
            $Articles = $Order->getArticles();
            $empty    = [];

            foreach ($Articles as $key => $Article) {
                if (!$Article->getQuantity()) {
                    $empty[] = $key;
                }
            }

            foreach ($empty as $pos) {
                $Order->removeArticle($pos);
            }

            $Order->save();

            $BasketOrder = new QUI\ERP\Order\Basket\BasketOrder($Order->getHash());
            $products    = $BasketOrder->getProducts()->toArray();

            $this->Basket->import($products['products']);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    /**
     * @return bool
     */
    public function showNext()
    {
        return (bool)$this->Basket->count();
    }

    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody()
    {
        if ($this->Basket instanceof QUI\ERP\Order\Basket\BasketOrder) {
            $this->Basket->refresh();
        }

        $Engine = QUI::getTemplateManager()->getEngine();

        if (!$this->Basket->count()) {
            return $Engine->fetch(\dirname(__FILE__).'/BasketEmpty.html');
        }

        $BasketControl = new BasketControl([
            'editable' => $this->getAttribute('editable')
        ]);

        $BasketControl->setBasket($this->Basket);

        $Engine->assign([
            'BasketControl' => $BasketControl,
            'Basket'        => $this->Basket,
            'Order'         => $this->getAttribute('Order'),
            'this'          => $this
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/Basket.html');
    }

    /**
     * @return mixed|void
     */
    public function save()
    {
        $this->Basket->save();
    }
}
