<?php

/**
 * This file contains QUI\ERP\Order\Controls\Basket
 */

namespace QUI\ERP\Order\Controls\OrderProcess;

use QUI;
use QUI\ERP\Order\Controls\Basket\Basket as BasketControl;
use QUI\ERP\Order\OrderInterface;
use QUI\ERP\Order\Basket\Basket as BasketClass;
use QUI\ERP\Order\Basket\BasketOrder;
use QUI\ERP\Order\Basket\BasketGuest;
use QUI\Exception;

use function dirname;

/**
 * Class Basket
 * - Basket step
 *
 * @package QUI\ERP\Order\Basket
 */
class Basket extends QUI\ERP\Order\Controls\AbstractOrderingStep
{
    /**
     * @var BasketClass|BasketOrder|BasketGuest
     */
    protected BasketClass|BasketOrder|BasketGuest $Basket;

    /**
     * Basket constructor.
     *
     * @param array $attributes
     *
     * @throws QUI\Exception
     */
    public function __construct(array $attributes = [])
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

        $this->addCSSFile(dirname(__FILE__) . '/Basket.css');
        $this->addCSSClass('quiqqer-order-step-basket');
        $this->setAttribute('nodeName', 'section');

        $messages = $this->Basket->getFrontendMessages()->toArray();

        foreach ($messages as $message) {
            $this->getOrder()->addFrontendMessage($message['message']);
        }
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getName(QUI\Locale $Locale = null): string
    {
        return 'Basket';
    }

    /**
     * @return string
     */
    public function getIcon(): string
    {
        return 'fa fa-shopping-basket';
    }

    /**
     * @return BasketGuest|BasketClass|BasketOrder
     */
    public function getBasket(): BasketGuest|BasketClass|BasketOrder
    {
        return $this->Basket;
    }

    /**
     * @throws QUI\ERP\Order\Exception
     */
    public function validate(): void
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

            $Order = $this->Basket->getOrder();
            $Articles = $Order->getArticles();
            $empty = [];

            foreach ($Articles as $key => $Article) {
                if (!$Article->getQuantity()) {
                    $empty[] = $key;
                }
            }

            foreach ($empty as $pos) {
                $Order->removeArticle($pos);
            }

            $Order->update();

            $BasketOrder = new QUI\ERP\Order\Basket\BasketOrder($Order->getUUID());
            $products = $BasketOrder->getProducts()->toArray();

            $this->Basket->import($products['products']);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    /**
     * @return bool
     */
    public function showNext(): bool
    {
        if ($this->getAttribute('Order')) {
            /* @var $Order OrderInterface */
            $Order = $this->getAttribute('Order');

            if ($Order->count()) {
                return true;
            }
        }

        return (bool)$this->Basket->count();
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        if ($this->Basket instanceof QUI\ERP\Order\Basket\BasketOrder) {
            $this->Basket->refresh();
        }

        $Engine = QUI::getTemplateManager()->getEngine();

        if (!$this->Basket->count()) {
            return $Engine->fetch(dirname(__FILE__) . '/BasketEmpty.html');
        }

        $BasketControl = new BasketControl([
            'editable' => $this->getAttribute('editable')
        ]);

        $BasketControl->setBasket($this->Basket);

        $Engine->assign([
            'BasketControl' => $BasketControl,
            'Basket' => $this->Basket,
            'Order' => $this->getAttribute('Order'),
            'this' => $this
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/Basket.html');
    }

    /**
     * @return void
     * @throws Exception
     */
    public function save(): void
    {
        $this->Basket->save();
    }
}
