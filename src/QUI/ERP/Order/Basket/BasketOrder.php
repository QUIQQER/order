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
 * @package QUI\ERP\Order\Basket
 */
class BasketOrder
{
    /**
     * List of products
     *
     * @var QUI\ERP\Products\Product\ProductList
     */
    protected $List = array();

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
            throw new Exception(array(
                'quiqqer/order',
                'exception.basket.not.found'
            ), 404);
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
     */
    public function clear()
    {
        $this->List->clear();

        if ($this->hasOrder()) {
            try {
                $this->getOrder()->clearArticles();
            } catch (QUI\Exception $Exception) {
            }
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
    public function import($products = array())
    {
        $this->clear();

        if (!is_array($products)) {
            $products = array();
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
     * Save the basket
     */
    public function save()
    {
//        // save only product ids with custom fields, we need not more
//        $result   = array();
//        $products = $this->List->getProducts();
//
//        foreach ($products as $Product) {
//            /* @var $Product Product */
//            $fields = $Product->getFields();
//
//            $productData = array(
//                'id'          => $Product->getId(),
//                'title'       => $Product->getTitle(),
//                'description' => $Product->getDescription(),
//                'quantity'    => $Product->getQuantity(),
//                'fields'      => array()
//            );
//
//            /* @var $Field QUI\ERP\Products\Field\UniqueField */
//            foreach ($fields as $Field) {
//                if ($Field->isCustomField()) {
//                    $productData['fields'][] = $Field->getAttributes();
//                }
//            }
//
//            $result[] = $productData;
//        }
//
//        QUI::getDataBase()->update(
//            QUI\ERP\Order\Handler::getInstance()->tableBasket(),
//            array(
//                'products' => json_encode($result),
//                'hash'     => $this->hash
//            ),
//            array(
//                'id'  => $this->getId(),
//                'uid' => $this->User->getId()
//            )
//        );
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
        $result   = array();

        /* @var $Product Product */
        foreach ($products as $Product) {
            $fields = array();

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

            $result[] = array(
                'id'       => $Product->getId(),
                'quantity' => $Product->getQuantity(),
                'fields'   => $fields
            );
        }

        return array(
            'id'       => $this->getId(),
            'products' => $result
        );
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

    //endregion
}
