<?php

/**
 * This file contains QUI\ERP\Order\Controls\Basket\Basket
 */

namespace QUI\ERP\Order\Controls\Basket;

use QUI;
use QUI\ERP\Order\Basket\Basket as BasketClass;
use QUI\ERP\Order\Basket\BasketGuest;
use QUI\ERP\Order\Basket\BasketOrder;

use function dirname;
use function is_array;

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
     * @var BasketClass|BasketGuest|BasketOrder
     */
    protected BasketClass|BasketGuest|BasketOrder $Basket;

    /**
     * @var QUI\Projects\Project|null
     */
    protected ?QUI\Projects\Project $Project = null;

    /**
     * Basket constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setAttributes([
            'buttons' => true,
            'isLoading' => false,
            'editable' => true
        ]);

        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/Basket.css');

        $this->addCSSClass('quiqqer-order-controls-basket');

        $this->setAttributes([
            'data-qui' => 'package/quiqqer/order/bin/frontend/controls/basket/Basket'
        ]);
    }

    /**
     * @param BasketClass|BasketGuest|BasketOrder $Basket
     */
    public function setBasket(BasketClass|BasketGuest|BasketOrder $Basket): void
    {
        $this->Basket = $Basket;
    }

    /**
     * @return string
     * @throws QUI\Exception
     */
    public function getBody(): string
    {
        $ProductsLocale = QUI\ERP\Products\Handler\Products::getLocale();
        QUI\ERP\Products\Handler\Products::setLocale(QUI::getLocale());

        $Engine = QUI::getTemplateManager()->getEngine();
        $Products = $this->Basket->getProducts();

        $Products->setCurrency(QUI\ERP\Defaults::getUserCurrency());
        $Products->setUser(QUI::getUserBySession());
        $Products->recalculate();

        $View = $Products->getView(QUI::getLocale());
        $showArticleNumber = QUI\ERP\Order\Settings::getInstance()->get('orderProcess', 'showArticleNumberInBasket');

        QUI\ERP\Products\Handler\Products::setLocale($ProductsLocale);

        $Engine->assign([
            'data' => $View->toArray(),
            'Basket' => $this->Basket,
            'Project' => $this->Project,
            'Products' => $View,
            'products' => $View->getProducts(),
            'this' => $this,
            'showArticleNumber' => $showArticleNumber,
            'Utils' => new QUI\ERP\Order\Utils\Utils()
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/Basket.html');
    }

    /**
     * @param $fieldValueText
     * @return mixed|string
     */
    public function getValueText($fieldValueText): mixed
    {
        $current = QUI::getLocale()->getCurrent();

        if (!is_array($fieldValueText)) {
            return $fieldValueText;
        }

        if (isset($fieldValueText[$current])) {
            return $fieldValueText[$current];
        }

        return '';
    }

    /**
     * @return bool
     */
    public function isGuest(): bool
    {
        return QUI::getUsers()->isNobodyUser(QUI::getUserBySession());
    }

    /**
     * @return bool
     */
    public function isLoading(): bool
    {
        return $this->getAttribute('isLoading');
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
