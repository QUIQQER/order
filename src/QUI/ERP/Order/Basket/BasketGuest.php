<?php

/**
 * This file contains QUI\ERP\Order\Basket\Basket
 */

namespace QUI\ERP\Order\Basket;

use QUI;
use QUI\ERP\Products\Field\UniqueField;
use QUI\ERP\Products\Product\ProductList;
use QUI\ExceptionStack;

use function floatval;
use function is_array;
use function is_numeric;

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
     * @var ?QUI\ERP\Products\Product\ProductList
     */
    protected ?ProductList $List = null;

    /**
     * Basket constructor.
     */
    public function __construct()
    {
        $this->List = new ProductList();
        $this->List->duplicate = true;
        $this->List->setCurrency(QUI\ERP\Defaults::getUserCurrency());
    }

    /**
     * Clear the basket
     */
    public function clear(): void
    {
        $this->List->clear();
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->List->count();
    }

    //region product handling

    /**
     * Return the product list
     *
     * @return ?ProductList
     */
    public function getProducts(): ?ProductList
    {
        return $this->List;
    }

    /**
     * Add a product to the basket
     *
     * @param Product $Product
     */
    public function addProduct(Product $Product): void
    {
        try {
            $this->List->addProduct($Product);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    //endregion

    /**
     * Import the products to the basket
     *
     * @param array|null $products
     * @throws ExceptionStack
     */
    public function import(array | null $products = []): void
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
                $Real = QUI\ERP\Products\Handler\Products::getProduct((int)$productData['id']);

                if (!$Real->isActive()) {
                    continue;
                }

                if (isset($productData['quantity'])) {
                    if (!is_numeric($productData['quantity'])) {
                        $productData['quantity'] = 1;
                    }

                    $productData['quantity'] = floatval($productData['quantity']);

                    if ($productData['quantity'] < 0) {
                        $productData['quantity'] = 1;
                    }

                    $Product->setQuantity($productData['quantity']);
                }

                $this->List->addProduct($Product);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        try {
            $this->List->recalculation();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        QUI::getEvents()->fireEvent(
            'quiqqerBasketImport',
            [$this, $this->List]
        );
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
    public function toArray(): array
    {
        $Products = $this->getProducts();
        $products = $Products->getProducts();
        $result = [];

        foreach ($products as $Product) {
            if (!method_exists($Product, 'getQuantity')) {
                continue;
            }

            $fields = [];

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
                'id' => $Product->getId(),
                'quantity' => $Product->getQuantity(),
                'fields' => $fields
            ];
        }

        // calc data
        $calculations = [];
        $unformatted = [];

        try {
            $data = $Products->getFrontendView()->toArray();
            $unformatted = $Products->toArray();

            unset($data['attributes']);
            unset($data['products']);

            $calculations = $data;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return [
            'products' => $result,
            'calculations' => $calculations,
            'unformatted' => $unformatted
        ];
    }

    //region hash & orders

    /**
     * Does the basket have an assigned order?
     *
     * @return bool
     */
    public function hasOrder(): bool
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

    /**
     * Placeholder for compatibility to the main basket class
     */
    public function updateOrder()
    {
    }

    //endregion
}
