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
        $this->Articles->setCurrency($Order->getCurrency());

        $this->setAttributes($this->Order->getAttributes());
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
     * @return ProcessingStatus\Status
     */
    public function getProcessingStatus()
    {
        return $this->Order->getProcessingStatus();
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
     * @return string
     */
    public function getCreateDate()
    {
        $createDate = $this->Order->getCreateDate();
        $createDate = \strtotime($createDate);

        $DateFormatter = QUI::getLocale()->getDateFormatter(
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE
        );

        return $DateFormatter->format($createDate);
    }

    /**
     * @return bool|QUI\ERP\Shipping\ShippingStatus\Status
     */
    public function getShippingStatus()
    {
        return $this->Order->getShippingStatus();
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

    /**
     * @return array
     */
    public function getData()
    {
        return $this->Order->getData();
    }

    /**
     * @return QUI\ERP\Accounting\Calculations
     * @throws QUI\ERP\Exception
     * @throws QUI\Exception
     */
    public function getPriceCalculation()
    {
        return $this->Order->getPriceCalculation();
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
        return $this->Order->isPaid();
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
     *
     * @throws QUI\Exception
     * @throws QUI\ERP\Accounting\Invoice\Exception
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

    //region shipping

    /**
     * do nothing, its a view
     */
    public function setShipping(\QUI\ERP\Shipping\Api\ShippingInterface $Shipping)
    {
    }

    /**
     * @return QUI\ERP\Shipping\Types\ShippingEntry|null
     */
    public function getShipping()
    {
        return $this->Order->getShipping();
    }

    /**
     * do nothing, its a view
     */
    public function removeShipping()
    {
    }

    //endregion

    /**
     * do nothing, its a view
     */
    protected function saveFrontendMessages()
    {
    }

    /**
     * do nothing, its a view
     */
    public function addFrontendMessage($message)
    {
    }

    /**
     * @return QUI\ERP\Comments|null
     */
    public function getFrontendMessages()
    {
        return $this->Order->getFrontendMessages();
    }

    /**
     * do nothing, its a view
     */
    public function clearFrontendMessages()
    {
    }
}
