<?php

namespace QUI\ERP\SalesOrders;

if (!class_exists(SalesOrder::class, false)) {
    class SalesOrder
    {
        private bool $isRevision = false;
        private ?\QUI\ERP\Accounting\ArticleList $Articles = null;

        public function setCurrency(\QUI\ERP\Currency\Currency $Currency): void
        {
        }

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
            return $this->Articles ??= new \QUI\ERP\Accounting\ArticleList();
        }

        /** @var array<int|string, mixed> */
        private array $importedArticles = [];

        /** @return array<int|string, mixed> */
        public function getImportedArticles(): array
        {
            return $this->importedArticles;
        }

        /** @param array<int|string, mixed> $articles */
        public function importArticles(array $articles = []): void
        {
            $this->importedArticles = $articles;
            $this->Articles = new \QUI\ERP\Accounting\ArticleList($articles);
        }

        public function addHistory(string $message): void
        {
        }

        public function update(?\QUI\Interfaces\Users\User $PermissionUser = null): void
        {
        }

        public function isRevision(): bool
        {
            return $this->isRevision;
        }

        public function setIsRevision(bool $isRevision): void
        {
            $this->isRevision = $isRevision;
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
