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
class Small extends QUI\Control
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
        if (
            $Basket instanceof QUI\ERP\Order\Basket\Basket ||
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
    protected function onCreate()
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        $Products = $this->Basket->getProducts();

        if (!$Products) {
            return '';
        }

        $Products->setCurrency(QUI\ERP\Defaults::getUserCurrency());

        $ProductView = $Products->getView();
        $Project = $this->getProject();

        try {
            $OrderProcessSite = QUI\ERP\Order\Utils\Utils::getOrderProcess($Project);
        } catch (QUI\Exception $Exception) {
            $OrderProcessSite = $Project->firstChild();
        }

        try {
            $ShoppingCart = QUI\ERP\Order\Utils\Utils::getShoppingCart($Project);
        } catch (QUI\Exception $Exception) {
            $ShoppingCart = $Project->firstChild();
        }

        $Engine->assign([
            'data' => $ProductView->toArray(),
            'Basket' => $this->Basket,
            'Products' => $ProductView,
            'products' => $ProductView->getProducts(),
            'OrderProcess' => $OrderProcessSite,
            'checkoutUrl' => $OrderProcessSite->getUrlRewritten(),
            'shoppingCartUrl' => $ShoppingCart->getUrlRewritten()
        ]);

        return $Engine->fetch(\dirname(__FILE__) . '/Small.html');
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
    protected function getProject(): QUI\Projects\Project
    {
        if ($this->Project === null) {
            $this->Project = QUI::getProjectManager()->get();
        }

        return $this->Project;
    }

    //endregion
}
