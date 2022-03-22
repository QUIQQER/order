<?php

namespace QUI\ERP\Order;

use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Address;
use QUI\ERP\Comments;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Shipping\Api\ShippingInterface;
use QUI\ERP\Shipping\Types\ShippingEntry;
use QUI\ERP\User;

/**
 * Interface OrderInterface
 *
 * @package QUI\ERP\Order
 */
interface OrderInterface
{
    /**
     * It return the invoice, if an invoice exist for the order
     *
     * @return Invoice|InvoiceTemporary
     * @throws \QUI\ERP\Accounting\Invoice\Exception
     */
    public function getInvoice();

    /**
     * Exists an invoice for the order? is the order already posted?
     *
     * @return bool
     */
    public function isPosted(): bool;

    /**
     * Alias for isPosted
     *
     * @return bool
     */
    public function hasInvoice(): bool;


    /**
     * Return the order as an array
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Return the order id
     *
     * @return int|string
     */
    public function getId();

    /**
     * Is the order successful
     * -> Ist der Bestellablauf erfolgreich abgeschlossen
     *
     * @return int
     */
    public function isSuccessful(): int;

    /**
     * Return invoice address
     *
     * @return Address
     */
    public function getInvoiceAddress(): Address;

    /**
     * Return delivery address
     *
     * @return Address
     */
    public function getDeliveryAddress(): Address;

    /**
     * Return the order create date
     *
     * @return string
     */
    public function getCreateDate(): string;

    /**
     * Return extra data array
     *
     * @return array
     */
    public function getData(): array;

    /**
     * Return a data entry
     *
     * @param string $key
     * @return mixed|null
     */
    public function getDataEntry(string $key);

    /**
     * Return the hash
     *
     * @return string
     */
    public function getHash(): string;

    /**
     * Return the customer of the order
     *
     * @return User
     */
    public function getCustomer(): User;

    /**
     * Return the currency of the order
     * - At the moment its the default currency of the system
     * - If we want to have different currencies, this can be implemented here
     *
     * @return Currency
     */
    public function getCurrency(): Currency;

    /**
     * Has the order a delivery address?
     *
     * @return bool
     */
    public function hasDeliveryAddress(): bool;

    //region articles

    /**
     * Return the length of the article list
     *
     * @return int
     */
    public function count(): int;

    /**
     * Return the order articles list
     *
     * @return ArticleList
     */
    public function getArticles(): ArticleList;

    //endregion

    //region payments

    /**
     * Return the payment
     *
     * @return null|Payment
     */
    public function getPayment(): ?Payment;

    /**
     * Return the payment paid status information
     * - How many has already been paid
     * - How many must be paid
     *
     * @return array
     *
     * @throws \QUI\ERP\Exception
     */
    public function getPaidStatusInformation(): array;

    /**
     * Is the order already paid?
     *
     * @return bool
     */
    public function isPaid(): bool;

    /**
     * Return all transactions related to the order
     *
     * @return array
     */
    public function getTransactions(): array;

    //endregion

    //region shipping

    /**
     * Return the shipping from the order
     *
     * @return ShippingEntry|null
     */
    public function getShipping(): ?ShippingEntry;

    /**
     * Set a shipping to the order
     *
     * @param ShippingInterface $Shipping
     */
    public function setShipping(ShippingInterface $Shipping);

    /**
     * Remove the shipping from the order
     */
    public function removeShipping();

    //endregion

    //region comments

    /**
     * Return the comments
     *
     * @return null|Comments
     */
    public function getComments(): ?Comments;

    //endregion

    //region history

    /**
     * Return the history object
     *
     * @return null|Comments
     */
    public function getHistory(): ?Comments;

    //endregion

    //region frontend messages

    /**
     * @return null|Comments
     */
    public function getFrontendMessages(): ?Comments;

    /**
     * @param string $message
     * @return mixed
     */
    public function addFrontendMessage(string $message);

    //endregion
}
