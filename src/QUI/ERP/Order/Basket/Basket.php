<?php

/**
 * This file contains QUI\ERP\Order\Basket\Baseket
 */

namespace QUI\ERP\Order\Basket;

use QUI;
use QUI\ERP\Products\Product\ProductList;
use QUI\ERP\Order\Factory;
use QUI\ERP\Order\Handler;

/**
 * Class Basket
 * Coordinates the order process, (order -> payment -> invoice)
 *
 * @package QUI\ERP\Order\Basket
 */
class Basket
{
    /**
     * Basket id
     *
     * @var integer
     */
    protected $id;

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
     * @var string
     */
    protected $hash = null;

    /**
     * Basket constructor.
     *
     * @param integer|bool $basketId - ID of the basket
     * @param bool|QUI\Users\User $User
     *
     * @throws Exception
     */
    public function __construct($basketId, $User = false)
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

        $this->List->setCurrency(QUI\ERP\Defaults::getUserCurrency());

        try {
            $data = Handler::getInstance()->getBasketData($basketId, $User);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return;
        }

        if (isset($Exception)) {
            try {
                if ($Exception instanceof ExceptionBasketNotFound) {
                    $Basket = Factory::getInstance()->createBasket($User);
                    $data   = Handler::getInstance()->getBasketData($Basket->getId(), $User);
                }
            } catch (QUI\Exception $Exception) {
                throw new Exception(
                    $Exception->getMessage(),
                    $Exception->getCode()
                );
            }
        }

        $this->id   = $basketId;
        $this->User = $User;
        $this->hash = $data['hash'];

        $this->import(\json_decode($data['products'], true));
    }

    /**
     * Return the basket ID
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Clear the basket
     */
    public function clear()
    {
        $this->List->clear();


        return;

        // @todo überdenken
        // if the order is successful, then create a new
//        try {
//            $Order = $this->getOrder();
//        } catch (QUI\Exception $Exception) {
//            try {
//                $Order = $this->createNewOrder();
//            } catch (QUi\Exception $Exception) {
//                QUI\System\Log::writeException($Exception);
//
//                return;
//            }
//        }
//
//        if ($Order->isSuccessful()) {
//            try {
//                // create a new in process
//                $NewOrder   = QUI\ERP\Order\Factory::getInstance()->createOrderInProcess();
//                $this->hash = $NewOrder->getHash();
//            } catch (QUi\Exception $Exception) {
//                QUI\System\Log::writeDebugException($Exception);
//            }
//        }
    }

    /**
     * Set the basket as ordered successful
     */
    public function successful()
    {
        $this->List->clear();
        $this->hash = null;
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
     *
     * @throws QUI\Exception
     * @throws QUI\ERP\Products\Product\Exception
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

        if (!\is_array($products)) {
            $products = [];
        }

        $this->List = QUI\ERP\Order\Utils\Utils::importProductsToBasketList(
            $this->List,
            $products
        );
    }

    /**
     * Save the basket
     */
    public function save()
    {
        // save only product ids with custom fields, we need not more
        $result   = [];
        $products = $this->List->getProducts();

        foreach ($products as $Product) {
            /* @var $Product Product */
            $fields = $Product->getFields();

            /* @var $Field QUI\ERP\Products\Field\UniqueField */
            foreach ($fields as $Field) {
                $Field->setChangeableStatus(false);
            }

            $productData = [
                'id'          => $Product->getId(),
                'title'       => $Product->getTitle(),
                'description' => $Product->getDescription(),
                'quantity'    => $Product->getQuantity(),
                'fields'      => []
            ];

            /* @var $Field QUI\ERP\Products\Field\UniqueField */
            foreach ($fields as $Field) {
                if ($Field->isCustomField()) {
                    $productData['fields'][] = $Field->getAttributes();
                }
            }

            $result[] = $productData;
        }

        try {
            QUI::getDataBase()->update(
                QUI\ERP\Order\Handler::getInstance()->tableBasket(),
                [
                    'products' => \json_encode($result),
                    'hash'     => $this->hash
                ],
                [
                    'id'  => $this->getId(),
                    'uid' => $this->User->getId()
                ]
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Return the basket as array
     *
     * @return array
     */
    public function toArray()
    {
        QUI\System\Log::writeRecursive('##########');
        QUI\System\Log::writeRecursive($this->getProducts()->count());

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

                $fields[$Field->getId()] = $Field->getView()->getAttributes();
            }

            $result[] = [
                'id'       => $Product->getId(),
                'quantity' => $Product->getQuantity(),
                'fields'   => $fields
            ];
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
            'products'     => $result,
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
        if (empty($this->hash)) {
            return false;
        }

        try {
            $this->getOrder();
        } catch (QUi\Exception $Exception) {
            return false;
        }

        return true;
    }

    /**
     * Return the assigned order from the basket
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\ERP\Order\Exception
     */
    public function getOrder()
    {
        if ($this->hash === null) {
            throw new Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
                QUI\ERP\Order\Handler::ERROR_ORDER_NOT_FOUND
            );
        }

        return QUI\ERP\Order\Handler::getInstance()->getOrderByHash($this->hash);
    }

    /**
     * If the basket has an order, it set the basket data to the order
     * There is a comparison between goods basket and order
     *
     * @throws QUI\Exception
     */
    public function updateOrder()
    {
        try {
            $Order = $this->getOrder();
        } catch (QUI\Exception $Exception) {
            if ($Exception->getCode() !== QUI\ERP\Order\Handler::ERROR_ORDER_NOT_FOUND) {
                QUI\System\Log::writeDebugException($Exception);

                return;
            }

            $Order = $this->createNewOrder();
        }

        $this->toOrder($Order);
        $this->setHash($Order->getHash());
    }

    /**
     * @param QUI\ERP\Order\Order|QUI\ERP\Order\OrderInProcess $Order
     *
     * @throws QUI\ERP\Exception
     * @throws QUI\Exception
     * @throws QUI\ExceptionStack
     * @throws QUI\Permissions\Exception
     */
    public function toOrder($Order)
    {
        try {
            // insert basket products into the articles
            $Products = $this->getProducts()->calc();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return;
        }

        // update the data
        $products = $Products->getProducts();

        $Order->clear();

        foreach ($products as $Product) {
            try {
                /* @var QUI\ERP\Order\Basket\Product $Product */
                $Order->addArticle($Product->toArticle(null, false));
            } catch (QUI\Users\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        QUI::getEvents()->fireEvent(
            'quiqqerOrderBasketToOrder',
            [$this, $Order, $Products]
        );

        $PriceFactors = $Products->getPriceFactors();

        $Order->getArticles()->importPriceFactors(
            $PriceFactors->toErpPriceFactorList()
        );

        $Order->getArticles()->calc();
        $Order->save();
    }

    /**
     * @return QUI\ERP\Order\OrderInProcess
     *
     * @throws QUI\Exception
     * @throws QUI\ERP\Order\Exception
     */
    protected function createNewOrder()
    {
        $Orders = QUI\ERP\Order\Handler::getInstance();
        $User   = QUI::getUserBySession();

        // create a new order
        try {
            // select the last order in processing
            return $Orders->getLastOrderInProcessFromUser($User);
        } catch (QUI\Erp\Order\Exception $Exception) {
        }

        return QUI\ERP\Order\Factory::getInstance()->createOrderInProcess();
    }

    //endregion
}
