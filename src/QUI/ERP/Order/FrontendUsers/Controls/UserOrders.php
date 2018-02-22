<?php

/**
 * This file contains QUI\ERP\Order\FrontendUsers\Controls\UserOrders
 */

namespace QUI\ERP\Order\FrontendUsers\Controls;

use QUI;
use QUI\Control;
use QUI\FrontendUsers\Controls\Profile\ControlInterface;

/**
 * Class UserOrders
 *
 * @package QUI\ERP\Order\FrontendUSers\Controls
 */
class UserOrders extends Control implements ControlInterface
{
    /**
     * UserOrders constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = array())
    {
        $this->addCSSClass('quiqqer-order-profile-orders');
        $this->addCSSFile(dirname(__FILE__).'/UserOrders.css');

        $this->setAttributes([
            'data-qui' => 'package/quiqqer/order/bin/frontend/controls/frontendusers/Orders',
            'page'     => 1,
            'limit'    => 5
        ]);

        parent::__construct($attributes);
    }

    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Orders = QUI\ERP\Order\Handler::getInstance();
        $User   = QUI::getUserBySession();

        if ($this->getAttribute('User')) {
            $User = $this->getAttribute('User');
        }

        $limit        = (int)$this->getAttribute('limit');
        $sheetsMax    = 1;
        $sheetCurrent = (int)$this->getAttribute('page');
        $start        = ($sheetCurrent - 1) * $limit;

        $count = $Orders->countOrdersByUser($User);

        if ($count) {
            $sheetsMax = ceil($count / $limit);
        }

        $orders = $Orders->getOrdersByUser($User, [
            'order' => 'c_date DESC',
            'limit' => $start.','.$limit
        ]);

        foreach ($orders as $Order) {
            /* @var $Order QUI\ERP\Order\Order */
            $Order->setAttribute(
                'downloadLink',
                URL_OPT_DIR.'quiqqer/order/bin/frontend/order.pdf.php?order='.$Order->getHash()
            );
        }

        $Engine->assign(array(
            'orders'  => $orders,
            'this'    => $this,
            'Project' => $this->getProject(),
            'Site'    => $this->getSite(),

            'sheetsMax'    => $sheetsMax,
            'sheetCurrent' => $sheetCurrent,
            'sheetLimit'   => $limit,
            'sheetCount'   => $count
        ));

        return $Engine->fetch(dirname(__FILE__).'/UserOrders.html');
    }

    /**
     * @param QUI\ERP\Order\Order|QUI\ERP\Order\OrderInProcess $Order
     * @return string
     * @throws QUI\Exception
     */
    public function renderOrder($Order)
    {
        if (!($Order instanceof QUI\ERP\Order\Order) &&
            !($Order instanceof QUI\ERP\Order\OrderInProcess)) {
            return '';
        }

        $Engine   = QUI::getTemplateManager()->getEngine();
        $Articles = $Order->getArticles();
        $Invoice  = null;

        $Articles->calc();

        if ($Order->isPosted()) {
            $Invoice = $Order->getInvoice();
        }

        $Engine->assign(array(
            'this'     => $this,
            'Order'    => $Order,
            'Invoice'  => $Invoice,
            'Articles' => $Articles,
            'articles' => $Articles->getArticles(),
            'order'    => $Articles->toArray(),
            'Utils'    => new QUI\ERP\Order\Utils\Utils(),
            'orderUrl' => QUI\ERP\Order\Utils\Utils::getOrderProcessUrlForHash(
                $this->getProject(),
                $Order->getHash()
            )
        ));

        return $Engine->fetch(dirname(__FILE__).'/UserOrders.Order.html');
    }

    /**
     * @param QUI\ERP\Accounting\Article $Article
     * @return string
     *
     * @throws QUI\Exception
     */
    public function renderArticle(QUI\ERP\Accounting\Article $Article)
    {
        $Engine  = QUI::getTemplateManager()->getEngine();
        $Product = null;
        $Image   = null;

        try {
            $Product = QUI\ERP\Products\Handler\Products::getProductByProductNo(
                $Article->getArticleNo()
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        if (!empty($Product)) {
            try {
                $Image = $Product->getImage();
            } catch (QUI\Exception $Exception) {
            }
        }

        $Article->calc();

        $Engine->assign(array(
            'this'    => $this,
            'Article' => $Article,
            'Product' => $Product,
            'Image'   => $Image,
            'Project' => QUI::getProjectManager()->get()
        ));

        return $Engine->fetch(dirname(__FILE__).'/UserOrders.Article.html');
    }

    /**
     * @return mixed|QUI\Projects\Site
     * @throws QUI\Exception
     */
    public function getSite()
    {
        if ($this->getAttribute('Site')) {
            return $this->getAttribute('Site');
        }

        $Site = QUI::getRewrite()->getSite();
        $this->setAttribute('Site', $Site);

        return $Site;
    }

    /**
     * event: on save
     */
    public function onSave()
    {
    }

    public function validate()
    {
    }
}
