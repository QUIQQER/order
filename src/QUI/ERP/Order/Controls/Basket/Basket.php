<?php

/**
 * This file contains QUI\ERP\Order\Controls\Basket\Basket
 */

namespace QUI\ERP\Order\Controls\Basket;

use QUI;

/**
 * Class Basket
 * - The main Basket control - display a basket
 *
 * @package QUI\ERP\Order\Controls\Basket
 */
class Basket extends QUI\Control
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
            $Basket instanceof QUI\ERP\Order\Basket\BasketGuest ||
            $Basket instanceof QUI\ERP\Order\Basket\BasketOrder
        ) {
            $this->Basket = $Basket;
        }
    }

    /**
     * @return string
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $Engine   = QUI::getTemplateManager()->getEngine();
        $Products = $this->Basket->getProducts();

        $Products->setCurrency(QUI\ERP\Defaults::getUserCurrency());
        $Products->setUser(QUI::getUserBySession());
        $Products->recalculate();

        $View = $Products->getView(QUI::getLocale());

        $Engine->assign([
            'data'     => $View->toArray(),
            'Basket'   => $this->Basket,
            'Project'  => $this->Project,
            'Products' => $View,
            'products' => $View->getProducts(),
            'this'     => $this
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/Basket.html');
    }

    /**
     * @param $fieldValueText
     * @return mixed|string
     */
    public function getValueText($fieldValueText)
    {
        $current = QUI::getLocale()->getCurrent();

        if (!\is_array($fieldValueText)) {
            return $fieldValueText;
        }

        if (isset($fieldValueText[$current])) {
            return $fieldValueText[$current];
        }

        return '';
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
