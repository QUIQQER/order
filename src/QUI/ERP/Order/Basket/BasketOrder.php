<?php

/**
 * This file contains QUI\ERP\Order\Basket\BasketOrder
 */

namespace QUI\ERP\Order\Basket;

use QUI;
use QUI\ERP\Order\Utils\Utils as OrderProductUtils;
use QUI\ERP\Products\Field\UniqueField;
use QUI\ERP\Products\Product\ProductList;
use QUI\Exception;
use QUI\ExceptionStack;

use function boolval;
use function is_array;

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
     * @var ?QUI\ERP\Products\Product\ProductList
     */
    protected ?ProductList $List = null;

    /**
     * @var ?QUI\Interfaces\Users\User
     */
    protected ?QUI\Interfaces\Users\User $User = null;

    /**
     * @var ?QUI\ERP\Order\AbstractOrder
     */
    protected ?QUI\ERP\Order\AbstractOrder $Order = null;

    /**
     * @var ?string
     */
    protected ?string $hash = null;

    /**
     * @var int|null
     */
    protected int | null $id = null;

    /**
     * @var QUI\ERP\Comments|null
     */
    protected ?QUI\ERP\Comments $FrontendMessages = null;

    /**
     * Basket constructor.
     *
     * @param string $orderHash - ID of the order
     * @param ?QUI\Interfaces\Users\User $User
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function __construct(string $orderHash, null | QUI\Interfaces\Users\User $User = null)
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

        $this->FrontendMessages = new QUI\ERP\Comments();
        $this->readOrder();

        // get basket id
        try {
            $Basket = QUI\ERP\Order\Handler::getInstance()->getBasketByHash(
                $this->Order->getUUID(),
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
    public function refresh(): void
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
    protected function readOrder(): void
    {
        $this->Order = QUI\ERP\Order\Handler::getInstance()->getOrderByHash($this->hash);
        $this->Order->refresh();

        $data = $this->Order->getArticles()->toArray();
        $priceFactors = $this->Order->getArticles()->getPriceFactors()->toArray();

        $articles = $data['articles'];

        $this->List = new ProductList();
        $this->List->duplicate = true;

        $this->List->setOrder($this->Order);
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
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Clear the basket
     * This method clears only  the order articles
     * if you want to clear the order, you must use Order->clear()
     */
    public function clear(): void
    {
        $this->List->clear();

        if ($this->hasOrder()) {
            $this->getOrder()->getArticles()->clear();
        }
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
     * @throws QUI\Exception
     */
    public function addProduct(Product $Product): void
    {
        $Package = QUI::getPackage('quiqqer/order');
        $Config = $Package->getConfig();
        $merge = boolval($Config->getValue('orderProcess', 'mergeSameProducts'));

        if (!$merge) {
            $this->List->addProduct($Product);

            if ($this->hasOrder()) {
                $this->updateOrder();
            }

            return;
        }


        $Products = $this->List->getProducts();
        $foundProduct = false;

        foreach ($Products as $P) {
            if (
                !method_exists($Product, 'toArray')
                || !method_exists($Product, 'getQuantity')
                || !method_exists($P, 'toArray')
                || !method_exists($P, 'getQuantity')
                || !method_exists($P, 'setQuantity')
            ) {
                continue;
            }

            $p1 = OrderProductUtils::getCompareProductArray($Product->toArray());
            $p2 = OrderProductUtils::getCompareProductArray($P->toArray());

            if ($p1 == $p2) {
                $foundProduct = true;
                $quantity = $P->getQuantity();
                $quantity = $quantity + $Product->getQuantity();

                $P->setQuantity($quantity);
                break;
            }
        }

        if ($foundProduct === false) {
            $this->List->addProduct($Product);
        }

        if ($this->hasOrder()) {
            $this->updateOrder();
        }
    }

    /**
     * Removes a position from the products
     *
     * @param integer $pos
     *
     * @throws QUI\ERP\Exception
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function removePosition(int $pos): void
    {
        if (!$this->hasOrder()) {
            return;
        }

        $this->getOrder()->removeArticle($pos);
        $this->getOrder()->update();

        $this->readOrder();
    }

    //endregion

    /**
     * Import the products to the basket
     *
     * @param array $products
     * @throws Exception
     * @throws ExceptionStack
     */
    public function import(array $products = []): void
    {
        $this->clear();

        if (!is_array($products)) {
            $products = [];
        }

        $Package = QUI::getPackage('quiqqer/order');
        $Config = $Package->getConfig();
        $merge = boolval($Config->getValue('orderProcess', 'mergeSameProducts'));

        if ($merge) {
            $products = OrderProductUtils::getMergedProductList($products);
        }

        QUI::getEvents()->fireEvent(
            'quiqqerOrderBasketToOrderBegin',
            [$this, $this->Order, &$products]
        );

        $this->List = QUI\ERP\Order\Utils\Utils::importProductsToBasketList(
            $this->List,
            $products,
            $this->getOrder()
        );

        try {
            $this->List->recalculate();
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

        QUI::getEvents()->fireEvent(
            'quiqqerBasketImport',
            [$this, $this->List]
        );
    }

    /**
     * Save the basket -> order
     *
     * @throws QUI\Exception
     */
    public function save(): void
    {
        $this->updateOrder();
    }

    /**
     * @throws Exception
     * @throws ExceptionBasketNotFound
     * @throws QUI\Database\Exception
     */
    public function saveToSessionBasket(): void
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
    public function toArray(): array
    {
        $Products = $this->getProducts();
        $products = $Products->getProducts();
        $result = [];

        foreach ($products as $Product) {
            $fields = [];

            /* @var $Field UniqueField */
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
                'id' => $Product->getId(),
                'quantity' => method_exists($Product, 'getQuantity') ? $Product->getQuantity() : 1,
                'fields' => $fields
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
            'id' => $this->getId(),
            'orderHash' => $this->getOrder()->getUUID(),
            'products' => $result,
            'priceFactors' => $Products->getPriceFactors()->toArray(),
            'calculations' => $calculations
        ];
    }

    //region hash & orders

    /**
     * Set the process number
     * - Vorgangsnummer
     *
     * @param string $hash
     */
    public function setHash(string $hash): void
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
        return true;
    }

    /**
     * Return the assigned order from the basket
     *
     * @return ?QUI\ERP\Order\AbstractOrder
     */
    public function getOrder(): ?QUI\ERP\Order\AbstractOrder
    {
        return $this->Order;
    }

    /**
     * placeholder for api
     *
     * @throws QUI\Exception
     */
    public function updateOrder(): void
    {
        $this->Order->getArticles()->clear();
        $products = $this->List->getProducts();

        foreach ($products as $Product) {
            try {
                if (method_exists($Product, 'toArticle')) {
                    $this->Order->addArticle($Product->toArticle(null, false));
                }
            } catch (QUI\Users\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }


        $Articles = $this->Order->getArticles();
        $OrderCurrency = $this->Order->getCurrency();

        $PriceFactors = $this->List->getPriceFactors();
        $PriceFactors->setCurrency($OrderCurrency);

        $Articles->setCurrency($OrderCurrency);
        $Articles->importPriceFactors($PriceFactors->toErpPriceFactorList());
        $Articles->recalculate();

        $this->Order->update();
    }

    /**
     * @throws QUI\Exception
     */
    public function toOrder(): void
    {
        $this->updateOrder();
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
