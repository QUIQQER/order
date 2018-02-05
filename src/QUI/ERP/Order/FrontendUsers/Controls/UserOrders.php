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
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__).'/UserOrders.css');
    }

    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $User   = QUI::getUserBySession();
        $orders = QUI\ERP\Order\Handler::getInstance()->getOrdersByUser($User);

        foreach ($orders as $Order) {
            /* @var $Order QUI\ERP\Order\Order */
            $Order->setAttribute(
                'downloadLink',
                URL_OPT_DIR.'quiqqer/order/bin/frontend/order.pdf.php?order='.$Order->getHash()
            );
        }

        $Engine->assign(array(
            'orders' => $orders,
            'this'   => $this
        ));

        return $Engine->fetch(dirname(__FILE__).'/UserOrders.html');
    }

    /**
     * @param QUI\ERP\Order\Order $Order
     * @return string
     * @throws QUI\Exception
     */
    public function renderOrder(QUI\ERP\Order\Order $Order)
    {
        $Engine   = QUI::getTemplateManager()->getEngine();
        $Articles = $Order->getArticles();

        $Articles->calc();

        /* @var $Article QUI\ERP\Accounting\Article */
        //$Article->getSum();

        $Engine->assign(array(
            'this'     => $this,
            'Order'    => $Order,
            'Articles' => $Articles,
            'articles' => $Articles->getArticles(),
            'order'    => $Articles->toArray()
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
     * event: on save
     */
    public function onSave()
    {
    }

    public function validate()
    {
    }
}
