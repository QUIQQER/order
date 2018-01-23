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
        $products = $this->Basket->getProducts()->getView()->getProducts();

        $Engine->assign(array(
            'Basket'   => $this->Basket,
            'products' => $products
        ));

        return $Engine->fetch(dirname(__FILE__).'/Small.html');
    }
}
