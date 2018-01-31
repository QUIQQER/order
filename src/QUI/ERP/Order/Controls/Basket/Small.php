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
     * @var
     */
    protected $Project;

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
        $Project  = $this->getProject();


        $Engine->assign(array(
            'data'         => $Products->toArray(),
            'Basket'       => $this->Basket,
            'Products'     => $Products,
            'products'     => $Products->getProducts(),
            'OrderProcess' => QUI\ERP\Order\Utils\Utils::getOrderProcess($Project)
        ));

        return $Engine->fetch(dirname(__FILE__).'/Small.html');
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
