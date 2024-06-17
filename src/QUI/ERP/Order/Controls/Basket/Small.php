<?php

/**
 * This file contains QUI\ERP\Order\Controls\Basket\Small
 */

namespace QUI\ERP\Order\Controls\Basket;

use Exception;
use QUI;
use QUI\ERP\Order\Basket\Basket;
use QUI\ERP\Order\Basket\BasketGuest;
use QUI\ERP\Order\Basket\BasketOrder;

use function dirname;

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
     * @var Basket|BasketGuest|BasketOrder
     */
    protected Basket|BasketGuest|BasketOrder $Basket;

    /**
     * @var ?QUI\Projects\Project
     */
    protected ?QUI\Projects\Project $Project = null;

    /**
     * @param Basket|BasketGuest|BasketOrder $Basket
     */
    public function setBasket(Basket|BasketGuest|BasketOrder $Basket): void
    {
        $this->Basket = $Basket;
    }

    /**
     * @return string
     * @throws QUI\Exception
     * @throws Exception
     */
    public function getBody(): string
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
        } catch (QUI\Exception) {
            $OrderProcessSite = $Project->firstChild();
        }

        try {
            $ShoppingCart = QUI\ERP\Order\Utils\Utils::getShoppingCart($Project);
        } catch (QUI\Exception) {
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

        return $Engine->fetch(dirname(__FILE__) . '/Small.html');
    }

    //region project

    /**
     * Set the used project
     *
     * @param QUI\Projects\Project $Project
     */
    public function setProject(QUI\Projects\Project $Project): void
    {
        $this->Project = $Project;
    }

    /**
     * @return QUI\Projects\Project
     * @throws Exception
     */
    protected function getProject(): QUI\Projects\Project
    {
        if ($this->Project) {
            return $this->Project;
        }

        return parent::getProject();
    }

    //endregion
}
