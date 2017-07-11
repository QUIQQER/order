<?php

/**
 * This file contains QUI\ERP\Order\Controls\Basket
 */

namespace QUI\ERP\Order\Controls;

use QUI;

/**
 * Class Basket
 * Basket display
 *
 * @package QUI\ERP\Order\Basket
 */
class Basket extends AbstractOrderingStep
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

        $this->addCSSFile(dirname(__FILE__) . '/Basket.css');
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'basket';
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
            throw new QUI\ERP\Order\Exception(array(
                'quiqqer/order',
                'exception.basket.has.no.articles'
            ));
        }
    }

    /**
     * @return string
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        $Articles = $this->Basket->getArticles()->toUniqueList();
        $Articles->hideHeader();

        $Engine->assign(array(
            'articles' => $Articles->toHTMLWithCSS(),
            'count'    => $Articles->count()
        ));

        return $Engine->fetch(dirname(__FILE__) . '/Basket.html');
    }


    public function save()
    {
        // TODO: Implement save() method.
    }
}
