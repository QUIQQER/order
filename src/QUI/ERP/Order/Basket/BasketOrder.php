<?php

/**
 * This file contains QUI\ERP\Order\Basket\Baseket
 */

namespace QUI\ERP\Order\Basket;

use QUI;
use QUI\ERP\Products\Product\ProductList;

/**
 * Class BasketOrder
 * Coordinates the order process, (order -> payment -> invoice)
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

        $this->List            = new ProductList();
        $this->List->duplicate = true;

        $this->Order = QUI\ERP\Order\Handler::getInstance()->getOrderByHash($orderHash);

        $this->User = $User;
        $this->hash = $orderHash;

        $data     = $this->Order->getArticles()->toArray();
        $articles = $data['articles'];

        $this->import($articles);
    }

    /**
     * Return the order ID
     *
     * @return int
     */
    public function getId()
    {
        return $this->Order->getId();
    }

    /**
     * Clear the basket
     * This method don't clear the order articles
     * if you want to clear the order articles, you must use Order->clear()
     */
    public function clear()
    {
        $this->List->clear();
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
            } catch (QUI\Exception $Exception) {
            }
        }
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

        foreach ($products as $productData) {
            if (!isset($productData['id'])) {
                continue;
            }

            try {
                $Product = new Product($productData['id'], $productData);

                // check if active
                $Real = QUI\ERP\Products\Handler\Products::getProduct($productData['id']);

                if (!$Real->isActive()) {
                    continue;
                }

                if (isset($productData['quantity'])) {
                    $Product->setQuantity($productData['quantity']);
                }

                $this->List->addProduct($Product);
            } catch (QUI\Exception $Exception) {
            }
        }
    }

    /**
     * Save the basket -> order
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

            $result[] = [
                'id'       => $Product->getId(),
                'quantity' => $Product->getQuantity(),
                'fields'   => $fields
            ];
        }

        return [
            'id'       => $this->getId(),
            'products' => $result
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
     */
    public function updateOrder()
    {
        $this->Order->save();
    }

    //endregion
}
