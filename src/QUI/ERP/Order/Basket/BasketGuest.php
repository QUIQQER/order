<?php

/**
 * This file contains QUI\ERP\Order\Basket\Baseket
 */

namespace QUI\ERP\Order\Basket;

use QUI;
use QUI\ERP\Products\Product\ProductList;

/**
 * Class BasketGuest
 * Basket for the guest
 *
 * @package QUI\ERP\Order\Basket
 */
class BasketGuest
{
    /**
     * List of products
     *
     * @var QUI\ERP\Products\Product\ProductList
     */
    protected $List = array();

    /**
     * Basket constructor.
     */
    public function __construct()
    {
        $this->List            = new ProductList();
        $this->List->duplicate = true;
    }

    /**
     * Clear the basket
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
     */
    public function addProduct(Product $Product)
    {
        $this->List->addProduct($Product);
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
     * placeholder
     */
    public function save()
    {
        // nothing to do
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
            'products' => $result
        );
    }

    //region hash & orders

    /**
     * Does the basket have an assigned order?
     *
     * @return bool
     */
    public function hasOrder()
    {
        return false;
    }

    /**
     * placeholder
     *
     * @throws Exception
     */
    public function getOrder()
    {
        throw new Exception(
            QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
            QUI\ERP\Order\Handler::ERROR_ORDER_NOT_FOUND
        );
    }

    //endregion
}
