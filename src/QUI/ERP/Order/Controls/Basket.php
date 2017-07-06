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

        $this->addCSSFile(dirname(__FILE__) . '/Basket.css');
    }

    /**
     * @return QUI\ERP\Order\Basket\Basket
     */
    public function getBasket()
    {
        return $this->Basket;
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
}
