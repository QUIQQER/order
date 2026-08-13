<?php

namespace QUITests\ERP\Order\Fixtures;

use QUI\ERP\Order\AbstractOrderProcessProvider;
use QUI\ERP\Order\Handler;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderInProcess;
use ReflectionClass;

class TestableHandler extends Handler
{
    /** @var list<int|string> */
    private array $loadedOrderIds = [];

    /** @var list<int|string> */
    private array $loadedOrderProcessIds = [];

    private ?Order $resolvedOrder = null;

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

        return parent::getOrderByHash($hash);
    }

    public function getOrderInProcess($orderId): OrderInProcess
    {
        $this->loadedOrderProcessIds[] = $orderId;

        return (new ReflectionClass(OrderInProcess::class))->newInstanceWithoutConstructor();
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
