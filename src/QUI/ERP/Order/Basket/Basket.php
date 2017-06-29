<?php

/**
 * This file contains QUI\ERP\Order\Basket\Baseket
 */

namespace QUI\ERP\Order\Basket;

use DusanKasan\Knapsack\Collection;
use QUI;

use QUI\ERP\Order\Handler;
use QUI\ERP\Order\Factory;
use QUI\ERP\Order\OrderProcess;

/**
 * Class Basket
 * A Shopping Basket with roducts from an user
 *
 * @package QUI\ERP\Order\Basket
 */
class Basket
{
    /**
     * @var OrderProcess
     */
    protected $Order = null;

    /**
     * Basket constructor.
     *
     * @param $orderId
     */
    public function __construct($orderId = false)
    {
        $User   = QUI::getUserBySession();
        $Orders = Handler::getInstance();

        try {
            if ($orderId !== false) {
                $this->Order = $Orders->getOrderInProcess($orderId);
            }
        } catch (QUI\Erp\Order\Exception $Exception) {
        }

        if ($this->Order === null) {
            // select the last order in processing
        }


        $this->id   = $basketId;
        $this->User = $User;
    }

    /**
     * Create the order
     * (Kostenpflichtig bestellen, start the pay process)
     */
    public function createOrder()
    {
        $this->Order->createOrder();
    }

    /**
     * Return the watchlist ID
     *
     * @return int
     */
    public function getOrderId()
    {
        return $this->Order->getId();
    }

    /**
     * Return the article list
     *
     * @return QUI\ERP\Accounting\ArticleList
     */
    public function getArticles()
    {
        return $this->Order->getArticles();
    }

    /**
     * Add a article to the basket
     *
     * @param QUI\ERP\Accounting\Article $Article
     */
    public function addArticle(QUI\ERP\Accounting\Article $Article)
    {
        $this->Order->addArticle($Article);
    }

    /**
     * Clear the basket
     * All articles in the processing order would be deleted
     */
    public function clear()
    {
        $this->Order->clearArticles();
    }

    /**
     * Save the basket to the order
     */
    public function save()
    {
        $this->Order->update();
    }
}
