<?php

/**
 * This file contains QUI\ERP\Order\Basket\Baseket
 */

namespace QUI\ERP\Order\Basket;

use QUI;
use QUI\ERP\Products\Product\ProductList;

/**
 * Class BasketOrder
 *
 * This is a helper to represent an already send order for the order process
 *
 * @package QUI\ERP\Order\Basket
 */
class BasketOrder
{
    /**
     * List of products
     *
     * @var QUI\ERP\Products\Product\ProductList
     */
    protected $List = [];

    /**
     * @var QUI\Interfaces\Users\User
     */
    protected $User;

    /**
     * @var QUI\ERP\Order\Order|QUI\ERP\Order\OrderInProcess
     */
    protected $Order;

    /**
     * @var string
     */
    protected $hash = null;

    /**
     * @var null
     */
    protected $id = null;

    /**
     * Basket constructor.
     *
     * @param integer|bool $orderHash - ID of the order
     * @param bool|QUI\Users\User $User
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function __construct($orderHash, $User = false)
    {
        if (!$User) {
            $User = QUI::getUserBySession();
        }

        if (!QUI::getUsers()->isUser($User) || $User->getType() == QUI\Users\Nobody::class) {
            throw new Exception([
                'quiqqer/order',
                'exception.basket.not.found'
            ], 404);
        }

        $this->User = $User;
        $this->hash = $orderHash;

        $this->readOrder();

        // get basket id
        try {
            $Basket = QUI\ERP\Order\Handler::getInstance()->getBasketByHash(
                $this->Order->getHash(),
                $this->User
            );

            $this->id = $Basket->getId();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    /**
     * refresh the basket
     * - import the order stuff to the basket
     */
    public function refresh()
    {
        try {
            $this->readOrder();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    /**
     * imports the order data into the basket
     *
     * @throws Exception
     * @throws QUI\ERP\Exception
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     */
    protected function readOrder()
    {
        $this->Order = QUI\ERP\Order\Handler::getInstance()->getOrderByHash($this->hash);
        $this->Order->refresh();

        $data     = $this->Order->getArticles()->toArray();
        $articles = $data['articles'];

        $this->List            = new ProductList($data);
        $this->List->duplicate = true;
        $this->List->setCurrency($this->Order->getCurrency());

        $this->import($articles);

        // PriceFactors
        $factors = $this->Order->getArticles()->getPriceFactors()->toArray();

        foreach ($factors as $factor) {
            $this->List->getPriceFactors()->add(
                new QUI\ERP\Products\Utils\PriceFactor($factor)
            );
        }

        $this->List->recalculate();
        $this->Order->recalculate($this);
    }

    /**
     * Return the order ID
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Clear the basket
     * This method clears only  the order articles
     * if you want to clear the order, you must use Order->clear()
     */
    public function clear()
    {
        $this->List->clear();

        if ($this->hasOrder()) {
            $this->getOrder()->getArticles()->clear();
        }
    }

    /**
     * @return int
     */
    public function count()
    {
        return $this->List->count();
    }

    //region product handling

    /**
     * Return the product list
     *
     * @return ProductList
     */
    public function getProducts()
    {
        return $this->List;
    }

    /**
     * Add a product to the basket
     *
     * @param Product $Product
     * @throws QUI\Exception
     */
    public function addProduct(Product $Product)
    {
        $this->List->addProduct($Product);

        if ($this->hasOrder()) {
            try {
                $this->getOrder()->addArticle($Product->toArticle());
                $this->save();
            } catch (QUI\Exception $Exception) {
            }
        }
    }

    /**
     * Removes a position from the products
     *
     * @param integer $pos
     *
     * @throws Exception
     * @throws QUI\ERP\Exception
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function removePosition($pos)
    {
        if (!$this->hasOrder()) {
            return;
        }

        $this->getOrder()->removeArticle($pos);
        $this->getOrder()->save();

        $this->readOrder();
    }

    //endregion

    /**
     * Import the products to the basket
     *
     * @param array $products
     */
    public function import($products = [])
    {
        $this->clear();

        if (!is_array($products)) {
            $products = [];
        }

        $this->List = QUI\ERP\Order\Utils\Utils::importProductsToBasketList(
            $this->List,
            $products
        );
    }

    /**
     * Save the basket -> order
     *
     * @throws QUI\Exception
     */
    public function save()
    {
        $this->updateOrder();
    }

    /**
     * Return the basket as array
     *
     * @return array
     */
    public function toArray()
    {
        $Products = $this->getProducts();
        $products = $Products->getProducts();
        $result   = [];

        /* @var $Product Product */
        foreach ($products as $Product) {
            $fields = [];

            /* @var $Field \QUI\ERP\Products\Field\UniqueField */
            foreach ($Product->getFields() as $Field) {
                if (!$Field->isPublic()) {
                    continue;
                }

                if (!$Field->isCustomField()) {
                    continue;
                }

                $fields[$Field->getId()] = $Field->getValue();
            }

            $attributes = [
                'id'       => $Product->getId(),
                'quantity' => $Product->getQuantity(),
                'fields'   => $fields
            ];

            $result[] = $attributes;
        }

        return [
            'id'           => $this->getId(),
            'orderHash'    => $this->getOrder()->getHash(),
            'products'     => $result,
            'priceFactors' => $Products->getPriceFactors()->toArray()
        ];
    }

    //region hash & orders

    /**
     * Set the process number
     * - Vorgangsnummer
     *
     * @param $hash
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
    }

    /**
     * Return the process number
     *
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * Does the basket have an assigned order?
     *
     * @return bool
     */
    public function hasOrder()
    {
        return true;
    }

    /**
     * Return the assigned order from the basket
     *
     * @return QUI\ERP\Order\Order|QUI\ERP\Order\OrderInProcess
     */
    public function getOrder()
    {
        return $this->Order;
    }

    /**
     * placeholder for api
     *
     * @throws QUI\Exception
     */
    public function updateOrder()
    {
        $this->Order->save();
    }

    //endregion
}
