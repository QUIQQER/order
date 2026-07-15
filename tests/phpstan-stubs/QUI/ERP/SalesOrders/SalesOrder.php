<?php

namespace QUI\ERP\SalesOrders;

if (!class_exists(SalesOrder::class, false)) {
    class SalesOrder
    {
        public function getUUID(): string
        {
            return '';
        }

        public function getData(string $key): mixed
        {
            return null;
        }

        public function setData(string $key, mixed $value): void
        {
        }

        public function setAttributes(array $attributes): void
        {
        }

        public function getArticles(): \QUI\ERP\Accounting\ArticleList
        {
            return new \QUI\ERP\Accounting\ArticleList();
        }

        public function addHistory(string $message): void
        {
        }

        public function update(): void
        {
        }

        public function getShipping(): ?\QUI\ERP\Shipping\Api\ShippingInterface
        {
            return null;
        }

        public function getShippingStatus(): ?\QUI\ERP\Shipping\ShippingStatus\Status
        {
            return null;
        }
    }
}
