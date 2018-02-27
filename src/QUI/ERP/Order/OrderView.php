<?php

/**
 * This file contains QUI\ERP\Order\OrderView
 */

namespace QUI\ERP\Order;

use QUI;

/**
 * Class OrderView
 *
 * @package QUI\ERP\Order
 */
class OrderView extends QUI\QDOM implements OrderInterface
{
    /**
     * @var
     */
    protected $prefix;

    /**
     * @var Order
     */
    protected $Order;

    /**
     * @var \QUI\ERP\Accounting\ArticleList
     */
    protected $Articles;

    /**
     * OrderView constructor.
     *
     * @param Order $Order
     */
    public function __construct(Order $Order)
    {
        $this->Order    = $Order;
        $this->Articles = $this->Order->getArticles();
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->Order->toArray();
    }

    /**
     * @return string
     */
    public function getHash()
    {
        return $this->Order->getHash();
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->Order->getIdPrefix().$this->Order->getId();
    }

    /**
     * @return int
     */
    public function getCleanedId()
    {
        return $this->Order->getId();
    }

    /**
     * @return \QUI\ERP\User
     */
    public function getCustomer()
    {
        return $this->Order->getCustomer();
    }

    /**
     * @return null|\QUI\ERP\Comments
     */
    public function getComments()
    {
        return $this->Order->getComments();
    }

    /**
     * @return null|\QUI\ERP\Comments
     */
    public function getHistory()
    {
        return $this->Order->getHistory();
    }

    /**
     * @return \QUI\ERP\Currency\Currency
     */
    public function getCurrency()
    {
        return $this->Order->getCurrency();
    }

    /**
     * @return array
     */
    public function getTransactions()
    {
        return $this->Order->getTransactions();
    }

    /**
     * @return int
     */
    public function getCreateDate()
    {
        return $this->Order->getCreateDate();
    }

    /**
     * @return int
     */
    public function isSuccessful()
    {
        return $this->Order->isSuccessful();
    }

    /**
     * @return bool
     */
    public function isPosted()
    {
        return $this->Order->isPosted();
    }

    public function getData()
    {
        return $this->Order->getData();
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function getDataEntry($key)
    {
        return $this->Order->getDataEntry($key);
    }

    //region delivery address

    /**
     * @return \QUI\ERP\Address
     */
    public function getDeliveryAddress()
    {
        return $this->Order->getDeliveryAddress();
    }

    /**
     * @return bool
     */
    public function hasDeliveryAddress()
    {
        return $this->Order->hasDeliveryAddress();
    }

    //endregion

    //region payment

    /**
     * @return null|\QUI\ERP\Accounting\Payments\Types\Payment
     */
    public function getPayment()
    {
        return $this->Order->getPayment();
    }

    /**
     * @return array
     * @throws \QUI\ERP\Exception
     */
    public function getPaidStatusInformation()
    {
        return $this->Order->getPaidStatusInformation();
    }

    /**
     * @return bool
     */
    public function isPaid()
    {
        return $this->Order->isPosted();
    }

    //endregion

    //region invoice

    /**
     * @return \QUI\ERP\Address
     */
    public function getInvoiceAddress()
    {
        return $this->Order->getInvoiceAddress();
    }

    /**
     * @return \QUI\ERP\Accounting\Invoice\Invoice
     * @throws \QUI\ERP\Accounting\Invoice\Exception
     */
    public function getInvoice()
    {
        return $this->Order->getInvoice();
    }

    /**
     * @return bool
     */
    public function hasInvoice()
    {
        return $this->Order->hasInvoice();
    }

    //endregion

    //region articles

    public function count()
    {
        return $this->Articles->count();
    }

    /**
     * @return \QUI\ERP\Accounting\ArticleList
     */
    public function getArticles()
    {
        return $this->Articles;
    }

    //endregion
}
