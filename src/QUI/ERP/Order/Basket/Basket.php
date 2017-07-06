<?php

/**
 * This file contains QUI\ERP\Order\Basket\Baseket
 */

namespace QUI\ERP\Order\Basket;

use QUI;

use QUI\ERP\Order\Handler;
use QUI\ERP\Order\Factory;
use QUI\ERP\Order\OrderProcess;
use QUI\ERP\Products\Handler\Products;

/**
 * Class Basket
 * Coordinates the order process, (order -> payment -> invoice)
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
     * @param integer|bool $orderId - optional, if given the selected order
     */
    public function __construct($orderId = false)
    {
        $User   = QUI::getUserBySession();
        $Orders = Handler::getInstance();

        try {
            if ($orderId !== false) {
                $Order = $Orders->getOrderInProcess($orderId);

                if ($Order->getCustomer()->getId() == $User->getId()) {
                    $this->Order = $Order;
                }
            }
        } catch (QUI\Erp\Order\Exception $Exception) {
        }

        if ($this->Order === null) {
            try {
                // select the last order in processing
                $this->Order = $Orders->getLastOrderInProcessFromUser($User);
            } catch (QUI\Erp\Order\Exception $Exception) {
                // if no order exists, we create one
                $this->Order = Factory::getInstance()->createOrderProcess();
            }
        }

        $this->User = $User;
    }

    /**
     * Import a product array
     *
     * @param array $articles
     */
    public function import(array $articles)
    {
        $this->Order->clearArticles();

        foreach ($articles as $article) {
            try {
                $Product = Products::getProduct($article['id']);
                $Unique  = $Product->createUniqueProduct();

                if (isset($productData['quantity'])) {
                    $Unique->setQuantity($productData['quantity']);
                }

                $this->addArticle($Unique->toArticle());
            } catch (QUI\Exception $Exception) {
                QUI::getMessagesHandler()->addAttention(
                    $Exception->getMessage()
                );
            }
        }
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
