<?php

namespace QUITests\ERP\Order\Fixtures;

use QUI\ERP\Order\AbstractOrderProcessProvider;
use QUI\ERP\Order\Basket\Basket;
use QUI\ERP\Order\Handler;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderInProcess;
use QUI\Interfaces\Users\User;
use ReflectionClass;

class TestableHandler extends Handler
{
    /** @var list<int|string> */
    private array $loadedOrderIds = [];

    /** @var list<int|string> */
    private array $loadedOrderProcessIds = [];

    private ?Order $resolvedOrder = null;
    private ?OrderInProcess $resolvedOrderInProcess = null;
    private ?Basket $resolvedBasket = null;

    /** @var list<Order> */
    private ?array $resolvedUserOrders = null;

    /** @var list<AbstractOrderProcessProvider> */
    private array $orderProcessProviders = [];

    public function get(int | string $orderId): Order
    {
        $this->loadedOrderIds[] = $orderId;

        if ($this->resolvedOrder !== null) {
            return $this->resolvedOrder;
        }

        return (new ReflectionClass(Order::class))->newInstanceWithoutConstructor();
    }

    public function getOrderByHash(string $hash): OrderInProcess | Order
    {
        if ($this->resolvedOrder !== null) {
            return $this->resolvedOrder;
        }

        if ($this->resolvedOrderInProcess !== null) {
            return $this->resolvedOrderInProcess;
        }

        return parent::getOrderByHash($hash);
    }

    public function getOrderByGlobalProcessId(int | string $id): Order
    {
        if ($this->resolvedOrder !== null) {
            return $this->resolvedOrder;
        }

        return parent::getOrderByGlobalProcessId($id);
    }

    public function getOrdersByUser(User $User, array $params = []): array
    {
        if ($this->resolvedUserOrders !== null) {
            return $this->resolvedUserOrders;
        }

        return parent::getOrdersByUser($User, $params);
    }

    public function getOrderInProcess($orderId): OrderInProcess
    {
        $this->loadedOrderProcessIds[] = $orderId;

        return (new ReflectionClass(OrderInProcess::class))->newInstanceWithoutConstructor();
    }

    public function getOrderInProcessByHash(string $hash): OrderInProcess
    {
        if ($this->resolvedOrderInProcess !== null) {
            return $this->resolvedOrderInProcess;
        }

        return parent::getOrderInProcessByHash($hash);
    }

    public function getBasketByHash(string $hash, null | User $User = null): Basket
    {
        if ($this->resolvedBasket !== null) {
            return $this->resolvedBasket;
        }

        return parent::getBasketByHash($hash, $User);
    }

    /**
     * @return list<int|string>
     */
    public function getLoadedOrderIds(): array
    {
        return $this->loadedOrderIds;
    }

    /**
     * @return list<int|string>
     */
    public function getLoadedOrderProcessIds(): array
    {
        return $this->loadedOrderProcessIds;
    }

    public function clearLoadedIds(): void
    {
        $this->loadedOrderIds = [];
        $this->loadedOrderProcessIds = [];
    }

    public function setResolvedOrder(?Order $Order): void
    {
        $this->resolvedOrder = $Order;
    }

    public function setResolvedOrderInProcess(?OrderInProcess $OrderInProcess): void
    {
        $this->resolvedOrderInProcess = $OrderInProcess;
    }

    public function setResolvedBasket(?Basket $Basket): void
    {
        $this->resolvedBasket = $Basket;
    }

    /**
     * @param list<Order> $orders
     */
    public function setResolvedUserOrders(array $orders): void
    {
        $this->resolvedUserOrders = $orders;
    }

    /**
     * @param list<AbstractOrderProcessProvider> $providers
     */
    public function setOrderProcessProviders(array $providers): void
    {
        $this->orderProcessProviders = $providers;
    }

    /**
     * @return list<AbstractOrderProcessProvider>
     */
    public function getOrderProcessProvider(): array
    {
        return $this->orderProcessProviders;
    }
}
