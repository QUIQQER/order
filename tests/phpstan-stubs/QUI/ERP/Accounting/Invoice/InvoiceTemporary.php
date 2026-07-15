<?php

namespace QUI\ERP\Accounting\Invoice;

if (!class_exists(InvoiceTemporary::class, false)) {
    class InvoiceTemporary extends \QUI\QDOM
    {
        public function getUUID(): string
        {
            return '';
        }

        public function setCustomer(mixed $User): void
        {
        }

        public function setDeliveryAddress(array|\QUI\ERP\Address $address): void
        {
        }

        public function setData(string $key, mixed $value): void
        {
        }

        public function getArticles(): \QUI\ERP\Accounting\ArticleList
        {
            return new \QUI\ERP\Accounting\ArticleList();
        }

        public function save(?\QUI\Interfaces\Users\User $PermissionUser = null): void
        {
        }

        public function linkTransaction(
            \QUI\ERP\Accounting\Payments\Transactions\Transaction $Transaction
        ): void {
        }

        public function validate(): void
        {
        }

        public function post(?\QUI\Interfaces\Users\User $PermissionUser = null): Invoice
        {
            return new Invoice();
        }
    }
}
