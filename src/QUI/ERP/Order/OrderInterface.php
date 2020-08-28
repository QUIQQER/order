<?php

namespace QUI\ERP\Order;

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
     * @return \QUI\ERP\Accounting\Invoice\Invoice
     * @throws \QUI\ERP\Accounting\Invoice\Exception
     */
    public function getInvoice();

    /**
     * Exists an invoice for the order? is the order already posted?
     *
     * @return bool
     */
    public function isPosted();

    /**
     * Alias for isPosted
     *
     * @return bool
     */
    public function hasInvoice();


    /**
     * Return the order as an array
     *
     * @return array
     */
    public function toArray();

    /**
     * Return the order id
     *
     * @return integer
     */
    public function getId();

    /**
     * Is the order successful
     * -> Ist der Bestellablauf erfolgreich abgeschlossen
     *
     * @return int
     */
    public function isSuccessful();

    /**
     * Return invoice address
     *
     * @return \QUI\ERP\Address
     */
    public function getInvoiceAddress();

    /**
     * Return delivery address
     *
     * @return \QUI\ERP\Address
     */
    public function getDeliveryAddress();

    /**
     * Return the order create date
     *
     * @return integer
     */
    public function getCreateDate();

    /**
     * Return extra data array
     *
     * @return array
     */
    public function getData();

    /**
     * Return a data entry
     *
     * @param string $key
     * @return mixed|null
     */
    public function getDataEntry($key);

    /**
     * Return the hash
     *
     * @return string
     */
    public function getHash();

    /**
     * Return the customer of the order
     *
     * @return \QUI\ERP\User
     */
    public function getCustomer();

    /**
     * Return the currency of the order
     * - At the moment its the default currency of the system
     * - If we want to have different currencies, this can be implemented here
     *
     * @return \QUI\ERP\Currency\Currency
     */
    public function getCurrency();

    /**
     * Has the order a delivery address?
     *
     * @return bool
     */
    public function hasDeliveryAddress();

    //region articles

    /**
     * Return the length of the article list
     *
     * @return int
     */
    public function count();

    /**
     * Return the order articles list
     *
     * @return \QUI\ERP\Accounting\ArticleList
     */
    public function getArticles();

    //endregion

    //region payments

    /**
     * Return the payment
     *
     * @return null|\QUI\ERP\Accounting\Payments\Types\Payment
     */
    public function getPayment();

    /**
     * Return the payment paid status information
     * - How many has already been paid
     * - How many must be paid
     *
     * @return array
     *
     * @throws \QUI\ERP\Exception
     */
    public function getPaidStatusInformation();

    /**
     * Is the order already paid?
     *
     * @return bool
     */
    public function isPaid();

    /**
     * Return all transactions related to the order
     *
     * @return array
     */
    public function getTransactions();

    //endregion

    //region shipping

    /**
     * Return the shipping from the order
     *
     * @return \QUI\ERP\Shipping\Types\ShippingEntry|null
     */
    public function getShipping();

    /**
     * Set a shipping to the order
     *
     * @param \QUI\ERP\Shipping\Api\ShippingInterface $Shipping
     */
    public function setShipping(\QUI\ERP\Shipping\Api\ShippingInterface $Shipping);

    /**
     * Remove the shipping from the order
     */
    public function removeShipping();

    //endregion

    //region comments

    /**
     * Return the comments
     *
     * @return null|\QUI\ERP\Comments
     */
    public function getComments();

    //endregion

    //region history

    /**
     * Return the history object
     *
     * @return null|\QUI\ERP\Comments
     */
    public function getHistory();

    //endregion

    //region frontend messages

    /**
     * @return null|\QUI\ERP\Comments
     */
    public function getFrontendMessages();

    /**
     * @param string $message
     * @return mixed
     */
    public function addFrontendMessage(string $message);

    //endregion
}
