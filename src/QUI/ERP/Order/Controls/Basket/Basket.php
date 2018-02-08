<?php

/**
 * This file contains QUI\ERP\Order\Controls\Basket\Small
 */

namespace QUI\ERP\Order\Controls\Basket;

use QUI;

/**
 * Class Basket
 * - The main Basket control - display a basket
 *
 * @package QUI\ERP\Order\Controls\Basket
 */
class Basket extends QUI\Controls\Control
{
    /**
     * Used basket
     *
     * @var QUI\ERP\Order\Basket\Basket|QUI\ERP\Order\Basket\BasketGuest
     */
    protected $Basket;

    /**
     * @var
     */
    protected $Project;

    /**
     * @param QUI\ERP\Order\Basket\Basket|QUI\ERP\Order\Basket\BasketGuest $Basket
     */
    public function setBasket($Basket)
    {
        if ($Basket instanceof QUI\ERP\Order\Basket\Basket ||
            $Basket instanceof QUI\ERP\Order\Basket\BasketGuest
        ) {
            $this->Basket = $Basket;
        }
    }

    /**
     * @return string
     * @throws QUI\Exception
     */
    protected function onCreate()
    {
        $Engine   = QUI::getTemplateManager()->getEngine();
        $Products = $this->Basket->getProducts();

        $Products->setUser(QUI::getUserBySession());
        $Products->calc();

        $View = $Products->getView();

        $Engine->assign(array(
            'data'     => $View->toArray(),
            'Basket'   => $this->Basket,
            'Project'  => $this->Project,
            'Products' => $View,
            'products' => $View->getProducts()
        ));

        return $Engine->fetch(dirname(__FILE__).'/Basket.html');
    }

    //region project

    /**
     * Set the used project
     *
     * @param QUI\Projects\Project $Project
     */
    public function setProject(QUI\Projects\Project $Project)
    {
        $this->Project = $Project;
    }

    /**
     * @return QUI\Projects\Project
     *
     * @throws QUI\Exception
     */
    protected function getProject()
    {
        if ($this->Project === null) {
            $this->Project = QUI::getProjectManager()->get();
        }

        return $this->Project;
    }

    //endregion
}
