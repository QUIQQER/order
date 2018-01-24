<?php

/**
 * This file contains QUI\ERP\Order\Controls\Basket\Small
 */

namespace QUI\ERP\Order\Controls\Basket;

use QUI;

/**
 * Class Small
 *
 * @package QUI\ERP\Order\Controls\Basket
 */
class Small extends QUI\Controls\Control
{
    /**
     * Used basket
     *
     * @var QUI\ERP\Order\Basket\Basket
     */
    protected $Basket;

    /**
     * @param QUI\ERP\Order\Basket\Basket $Basket
     */
    public function setBasket(QUI\ERP\Order\Basket\Basket $Basket)
    {
        $this->Basket = $Basket;
    }

    /**
     * @return string
     * @throws QUI\Exception
     */
    protected function onCreate()
    {
        $Engine   = QUI::getTemplateManager()->getEngine();
        $Products = $this->Basket->getProducts()->getView();


        $Engine->assign(array(
            'data'     => $Products->toArray(),
            'Basket'   => $this->Basket,
            'Products' => $Products,
            'products' => $Products->getProducts()
        ));

        return $Engine->fetch(dirname(__FILE__).'/Small.html');
    }
}
