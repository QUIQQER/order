<?php

/**
 * This file contains QUI\ERP\Order\Basket\Basket
 */

namespace QUI\ERP\Order\Basket;

use QUI;
use QUI\Database\Exception;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Handler;
use QUI\ERP\Products\Field\UniqueField;
use QUI\ERP\Products\Product\ProductList;
use QUI\ExceptionStack;

use function is_array;
use function json_decode;
use function json_encode;

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
     * @var integer|bool
     */
    protected int | bool $id = false;

    /**
     * List of products
     *
     * @var QUI\ERP\Products\Product\ProductList|null
     */
    protected ?ProductList $List = null;

    /**
     * @var ?QUI\Interfaces\Users\User
     */
    protected ?QUI\Interfaces\Users\User $User = null;

    /**
     * @var ?string
     */
    protected ?string $hash = null;

    /**
     * @var QUI\ERP\Comments|null
     */
    protected ?QUI\ERP\Comments $FrontendMessages = null;

    /**
     * Basket constructor.
     *
     * @param bool|integer $basketId - ID of the basket
     * @param ?QUI\Interfaces\Users\User $User
     * @throws ExceptionStack
     */
    public function __construct(bool | int $basketId, null | QUI\Interfaces\Users\User $User = null)
    {
        if (!$User) {
            $User = QUI::getUserBySession();
        }

        $this->List = new ProductList();
        $this->List->duplicate = true;

        if (!QUI::getUsers()->isUser($User) || $User->getType() == QUI\Users\Nobody::class) {
            return;
        }

        $this->List->setUser($User);
        $this->FrontendMessages = new QUI\ERP\Comments();

        if (is_bool($basketId)) {
            try {
                $Basket = Handler::getInstance()->getBasketFromUser(QUI::getUserBySession());
                $basketId = $Basket->getId();
            } catch (QUI\Exception) {
            }
        }

        try {
            $data = Handler::getInstance()->getBasketData($basketId, $User);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return;
        }

        $this->id = $basketId;
        $this->User = $User;
        $this->hash = $data['hash'];

        if (!empty($data['products'])) {
            $this->import(json_decode($data['products'], true));
        }

        $this->List->setCurrency(QUI\ERP\Defaults::getUserCurrency());
    }

    /**
     * Return the basket ID
     *
     * @return bool|int
     */
    public function getId(): bool | int
    {
        return $this->id;
    }

    /**
     * Clear the basket
     */
    public function clear(): void
    {
        $this->List->clear();
    }

    /**
     * Set the basket as ordered successful
     */
    public function successful(): void
    {
        $this->List->clear();
        $this->hash = null;
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
     * @return ProductList
     */
    public function getProducts(): ProductList
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
    public function addProduct(Product $Product): void
    {
        $this->List->addProduct($Product);

        if ($this->hasOrder()) {
            try {
                $this->getOrder()->addArticle($Product->toArticle());
            } catch (QUI\Exception) {
            }
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

        $OrderOrBasket = $this;

        try {
            $Order = $this->getOrder();
            $this->List->setOrder($Order);

            $OrderOrBasket = $Order;
        } catch (QUI\Exception) {
        }

        $this->List = QUI\ERP\Order\Utils\Utils::importProductsToBasketList(
            $this->List,
            $products,
            $OrderOrBasket
        );

        try {
            $this->List->recalculate();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        $this->save();


        QUI::getEvents()->fireEvent(
            'quiqqerBasketImport',
            [$this, $this->List]
        );
    }

    /**
     * Save the basket
     */
    public function save(): void
    {
        if (!$this->List) {
            return;
        }

        if (!$this->User) {
            return;
        }

        // save only product ids with custom fields, we need not more
        $result = [];
        $products = $this->List->getProducts();

        foreach ($products as $Product) {
            if (
                !method_exists($Product, 'getUuid')
                || !method_exists($Product, 'getProductSetParentUuid')
                || !method_exists($Product, 'getQuantity')
                || !method_exists($Product, 'toArticle')
            ) {
                continue;
            }

            $fields = $Product->getFields();

            foreach ($fields as $Field) {
                $Field->setChangeableStatus(false);
            }

            $productData = [
                'id' => $Product->getId(),
                'uuid' => $Product->getUuid(),
                'productSetParentUuid' => $Product->getProductSetParentUuid(),
                'title' => $Product->getTitle(),
                'description' => $Product->getDescription(),
                'quantity' => $Product->getQuantity(),
                'fields' => []
            ];

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
                    'products' => json_encode($result),
                    'hash' => $this->hash
                ],
                [
                    'id' => $this->getId(),
                    'uid' => $this->User->getUUID()
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
    public function toArray(): array
    {
        $Products = $this->getProducts();
        $products = $Products->getProducts();
        $result = [];

        foreach ($products as $Product) {
            if (
                !method_exists($Product, 'getUuid')
                || !method_exists($Product, 'getProductSetParentUuid')
                || !method_exists($Product, 'getQuantity')
            ) {
                continue;
            }

            $fields = [];

            foreach ($Product->getFields() as $Field) {
                if (!$Field->isPublic() && !$Field->isCustomField()) {
                    continue;
                }

                $fields[$Field->getId()] = $Field->getView()->getAttributes();
            }

            $result[] = [
                'id' => $Product->getId(),
                'uuid' => $Product->getUuid(),
                'productSetParentUuid' => $Product->getProductSetParentUuid(),
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
            'id' => $this->getId(),
            'products' => $result,
            'calculations' => $calculations,
            'unformatted' => $unformatted
        ];
    }

    //region hash & orders

    /**
     * Set the process number
     * - Vorgangsnummer
     *
     * @param $hash
     */
    public function setHash($hash): void
    {
        $this->hash = $hash;
    }

    /**
     * Return the process number
     *
     * @return string|null
     */
    public function getHash(): ?string
    {
        return $this->hash;
    }

    /**
     * Does the basket have an assigned order?
     *
     * @return bool
     */
    public function hasOrder(): bool
    {
        if (empty($this->hash)) {
            return false;
        }

        try {
            $this->getOrder();
        } catch (QUi\Exception) {
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
    public function getOrder(): QUI\ERP\Order\AbstractOrder
    {
        if (empty($this->hash)) {
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
    public function updateOrder(): void
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
        $this->setHash($Order->getUUID());
    }

    /**
     * @param AbstractOrder $Order
     *
     * @throws ExceptionStack
     * @throws Exception
     * @throws QUI\ERP\Exception
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function toOrder(QUI\ERP\Order\AbstractOrder $Order): void
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

        $InvoiceAddress = $Order->getInvoiceAddress();
        $DeliveryAddress = $Order->getDeliveryAddress();

        $Order->clear();

        foreach ($products as $Product) {
            if (!method_exists($Product, 'toArticle')) {
                continue;
            }

            try {
                $Order->addArticle($Product->toArticle(null, false));
            } catch (QUI\Users\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        $Order->setInvoiceAddress($InvoiceAddress);
        $Order->setDeliveryAddress($DeliveryAddress);
        $Order->update();

        QUI::getEvents()->fireEvent(
            'quiqqerOrderBasketToOrder',
            [$this, $Order, $Products]
        );

        $PriceFactors = $Products->getPriceFactors();

        $Order->getArticles()->importPriceFactors(
            $PriceFactors->toErpPriceFactorList()
        );

        $Order->getArticles()->calc();
        $Order->update();

        QUI::getEvents()->fireEvent(
            'quiqqerOrderBasketToOrderEnd',
            [$this, $Order, $Products]
        );
    }

    /**
     * @return QUI\ERP\Order\OrderInProcess
     *
     * @throws QUI\Exception
     * @throws QUI\ERP\Order\Exception
     */
    protected function createNewOrder(): QUI\ERP\Order\OrderInProcess
    {
        $Orders = QUI\ERP\Order\Handler::getInstance();
        $User = QUI::getUserBySession();

        // create a new order
        try {
            // select the last order in processing
            return $Orders->getLastOrderInProcessFromUser($User);
        } catch (QUI\ERP\Order\Exception) {
        }

        return QUI\ERP\Order\Factory::getInstance()->createOrderInProcess();
    }

    //endregion

    //region frontend message

    /**
     * Add a frontend message
     *
     * @param string $message
     */
    public function addFrontendMessage(string $message): void
    {
        $this->FrontendMessages->addComment($message);
    }

    /**
     * Return the frontend message object
     *
     * @return null|QUI\ERP\Comments
     */
    public function getFrontendMessages(): ?QUI\ERP\Comments
    {
        return $this->FrontendMessages;
    }

    /**
     * Clears the messages and save this status to the database
     */
    public function clearFrontendMessages(): void
    {
        $this->FrontendMessages->clear();
    }

    //endregion
}
