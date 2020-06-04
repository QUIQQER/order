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
     * @throws QUI\ERP\Exception
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     */
    protected function readOrder()
    {
        $this->Order = QUI\ERP\Order\Handler::getInstance()->getOrderByHash($this->hash);
        $this->Order->refresh();

        $data         = $this->Order->getArticles()->toArray();
        $priceFactors = $this->Order->getArticles()->getPriceFactors()->toArray();

        $articles = $data['articles'];

        $this->List            = new ProductList();
        $this->List->duplicate = true;

        $this->List->setCurrency($this->Order->getCurrency());
        $this->List->getPriceFactors()->importList([
            'end' => $priceFactors
        ]);

        // note, import do a cleanup
        $this->import($articles);
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
     */
    public function addProduct(Product $Product)
    {
        /** @noinspection \QUI\Exception */
        $Package = QUI::getPackage('quiqqer/order');
        $Config  = $Package->getConfig();
        $merge   = \boolval($Config->getValue('orderProcess', 'mergeSameProducts'));

        if (!$merge) {
            $this->List->addProduct($Product);
        } else {
            $Products = $this->List->getProducts();

            foreach ($Products as $Product) {

            }
        }

        if ($this->hasOrder()) {
            if (!$merge) {
                $this->getOrder()->addArticle($Product->toArticle());
            }

            $this->save();
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

        if (!\is_array($products)) {
            $products = [];
        }

        $Package = QUI::getPackage('quiqqer/order');
        $Config  = $Package->getConfig();
        $merge   = \boolval($Config->getValue('orderProcess', 'mergeSameProducts'));

        if ($merge) {
            $newProductList = [];

            $getCompareProductArray = function ($product) {
                $compare = [];
                $needles = [
                    'id',
                    'title',
                    'articleNo',
                    'description',
                    'unitPrice',
                    'displayPrice',
                    'class',
                    'customFields',
                    'customData',
                    'display_unitPrice'
                ];

                foreach ($needles as $f) {
                    if (isset($product[$f])) {
                        $compare[$f] = $product[$f];
                    }
                }

                return $compare;
            };

            $getProductIndex = function ($product) use (&$newProductList, $getCompareProductArray) {
                foreach ($newProductList as $index => $p) {
                    $p1 = \serialize($getCompareProductArray($product));
                    $p2 = \serialize($getCompareProductArray($p));

                    if ($p1 == $p2) {
                        return $index;
                    }
                }

                return false;
            };

            foreach ($products as $product) {
                $index = $getProductIndex($product);

                if ($index !== false) {
                    $newProductList[$index]['quantity'] = $newProductList[$index]['quantity'] + $product['quantity'];
                    continue;
                }

                $newProductList[] = $product;
            }

            $products = $newProductList;
        }

        $this->List = QUI\ERP\Order\Utils\Utils::importProductsToBasketList(
            $this->List,
            $products
        );

        try {
            $this->List->calc();
            $this->save();

            QUI::getEvents()->fireEvent(
                'quiqqerOrderBasketToOrder',
                [$this, $this->Order, $this->List]
            );

            QUI::getEvents()->fireEvent(
                'quiqqerOrderBasketToOrderEnd',
                [$this, $this->Order, $this->List]
            );

            $this->List->calc();
            $this->save();
        } catch (\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());
        }
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
     * @throws Exception
     * @throws ExceptionBasketNotFound
     * @throws QUI\Database\Exception
     */
    public function saveToSessionBasket()
    {
        $data = $this->toArray();

        $Basket = QUI\ERP\Order\Handler::getInstance()->getBasketFromUser($this->User);
        $Basket->import($data['products']);
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

        // calc data
        $calculations = [];

        try {
            $data = $Products->getFrontendView()->toArray();

            unset($data['attributes']);
            unset($data['products']);

            $calculations = $data;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }


        return [
            'id'           => $this->getId(),
            'orderHash'    => $this->getOrder()->getHash(),
            'products'     => $result,
            'priceFactors' => $Products->getPriceFactors()->toArray(),
            'calculations' => $calculations
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
        $this->Order->getArticles()->clear();
        $products = $this->List->getProducts();

        foreach ($products as $Product) {
            try {
                /* @var QUI\ERP\Order\Basket\Product $Product */
                $this->Order->addArticle($Product->toArticle(null, false));
            } catch (QUI\Users\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }


        $PriceFactors = $this->List->getPriceFactors();

        $this->Order->getArticles()->importPriceFactors(
            $PriceFactors->toErpPriceFactorList()
        );

        $this->Order->getArticles()->recalculate();
        $this->Order->save();
    }

    /**
     * @throws QUI\Exception
     */
    public function toOrder()
    {
        $this->updateOrder();
    }

    //endregion
}
