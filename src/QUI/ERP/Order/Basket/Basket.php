<?php

/**
 * This file contains QUI\ERP\Order\Basket\Baseket
 */

namespace QUI\ERP\Order\Basket;

use DusanKasan\Knapsack\Collection;
use QUI;

/**
 * Class Basket
 * A Shopping Basket with roducts from an user
 *
 * @package QUI\ERP\Order\Basket
 */
class Basket
{
    const STATUS_DEFAULT = 0;

    const STATUS_IN_PROGRESS = 1;

    const STATUS_ARCHIVE = 2;

    /**
     * @var Collection|null
     */
    protected $List = null;

    /**
     * @var
     */
    protected $id;

    /**
     * @var null|QUI\Interfaces\Users\User
     */
    protected $User;

    /**
     * Basket constructor.
     *
     * @param $basketId
     * @param null $User
     */
    public function __construct($basketId, $User = null)
    {
        if ($User === null) {
            $User = QUI::getUserBySession();
        }

        $data = Handler::getInstance()->getBasketData($basketId, $User->getId());

        $this->id   = $basketId;
        $this->User = $User;

        $this->List = Collection::from([]);
    }


    public function serialize()
    {

    }

    public function unserialize()
    {

    }

    public function createOrder()
    {

    }


    /**
     * Return the watchlist ID
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Return the article list
     *
     * @return Collection
     */
    public function getArticles()
    {
        return $this->List;
    }

    /**
     * Add a article to the basket
     *
     * @param Article $Article
     */
    public function addArticle(Article $Article)
    {
        $this->List->append($Article);
    }

    /**
     * Clear the watchlist
     */
    public function clear()
    {
        $this->List = new Collection([]);
    }

    /**
     * Save the watchlist
     */
    public function save()
    {
        // save only product ids with custom fields, we need not more
        $result   = array();
        $articles = $this->List->toArray();

        foreach ($articles as $Article) {
            /* @var $Article Article */
            $fields = $Article->getFields();

            $productData = array(
                'id'          => $Article->getId(),
                'title'       => $Article->getTitle(),
                'description' => $Article->getDescription(),
                'quantity'    => $Article->getQuantity(),
                'fields'      => array()
            );

            /* @var $Field QUI\ERP\Products\Field\UniqueField */
            foreach ($fields as $Field) {
                if ($Field->isCustomField()) {
                    $productData['fields'][] = $Field->getAttributes();
                }
            }

            $result[] = $productData;
        }

        QUI::getDataBase()->update(
            Handler::getInstance()->table(),
            array(
                'articles' => json_encode($result)
            ),
            array(
                'id'  => $this->getId(),
                'uid' => $this->User->getId()
            )
        );
    }
}
