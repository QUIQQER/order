<?php

/**
 * This file contains QUI\ERP\Order\OrderView
 */

namespace QUI\ERP\Order;

use IntlDateFormatter;
use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Address;
use QUI\ERP\Comments;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Shipping\Api\ShippingInterface;

use function strtotime;

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
     * @var ArticleList
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
    public function toArray(): array
    {
        return $this->Order->toArray();
    }

    /**
     * @return string
     */
    public function getHash(): string
    {
        return $this->Order->getHash();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->Order->getIdPrefix() . $this->Order->getId();
    }

    /**
     * @return ProcessingStatus\Status
     */
    public function getProcessingStatus(): ProcessingStatus\Status
    {
        return $this->Order->getProcessingStatus();
    }

    /**
     * @return int
     */
    public function getCleanedId(): int
    {
        return $this->Order->getId();
    }

    /**
     * @return QUI\ERP\User
     */
    public function getCustomer(): QUI\ERP\User
    {
        return $this->Order->getCustomer();
    }

    /**
     * @return null|Comments
     */
    public function getComments(): ?Comments
    {
        return $this->Order->getComments();
    }

    /**
     * @return null|Comments
     */
    public function getHistory(): ?Comments
    {
        return $this->Order->getHistory();
    }

    /**
     * @return Currency
     */
    public function getCurrency(): Currency
    {
        return $this->Order->getCurrency();
    }

    /**
     * @return array
     */
    public function getTransactions(): array
    {
        return $this->Order->getTransactions();
    }

    /**
     * @return string
     */
    public function getCreateDate(): string
    {
        $createDate = $this->Order->getCreateDate();
        $createDate = strtotime($createDate);

        $DateFormatter = QUI::getLocale()->getDateFormatter(
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE
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
    public function isSuccessful(): int
    {
        return $this->Order->isSuccessful();
    }

    /**
     * @return bool
     */
    public function isPosted(): bool
    {
        return $this->Order->isPosted();
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->Order->getData();
    }

    /**
     * @return QUI\ERP\Accounting\Calculations
     * @throws QUI\ERP\Exception
     * @throws QUI\Exception
     */
    public function getPriceCalculation(): QUI\ERP\Accounting\Calculations
    {
        return $this->Order->getPriceCalculation();
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function getDataEntry(string $key)
    {
        return $this->Order->getDataEntry($key);
    }

    //region delivery address

    /**
     * @return Address
     */
    public function getDeliveryAddress(): QUI\ERP\Address
    {
        return $this->Order->getDeliveryAddress();
    }

    /**
     * @return bool
     */
    public function hasDeliveryAddress(): bool
    {
        return $this->Order->hasDeliveryAddress();
    }

    //endregion

    //region payment

    /**
     * @return null|Payment
     */
    public function getPayment(): ?Payment
    {
        return $this->Order->getPayment();
    }

    /**
     * @return array
     * @throws \QUI\ERP\Exception
     */
    public function getPaidStatusInformation(): array
    {
        return $this->Order->getPaidStatusInformation();
    }

    /**
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->Order->isPaid();
    }

    //endregion

    //region invoice

    /**
     * @return Address
     */
    public function getInvoiceAddress(): Address
    {
        return $this->Order->getInvoiceAddress();
    }

    /**
     * @return QUI\ERP\Accounting\Invoice\Invoice|QUI\ERP\Accounting\Invoice\InvoiceTemporary
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
    public function hasInvoice(): bool
    {
        return $this->Order->hasInvoice();
    }

    //endregion

    //region articles

    public function count(): int
    {
        return $this->Articles->count();
    }

    /**
     * @return ArticleList
     */
    public function getArticles(): ArticleList
    {
        return $this->Articles;
    }

    //endregion

    //region shipping

    /**
     * do nothing, its a view
     */
    public function setShipping(ShippingInterface $Shipping)
    {
    }

    /**
     * @return QUI\ERP\Shipping\Types\ShippingEntry|null
     */
    public function getShipping(): ?QUI\ERP\Shipping\Types\ShippingEntry
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
     * @return Comments|null
     */
    public function getFrontendMessages(): ?Comments
    {
        return $this->Order->getFrontendMessages();
    }

    /**
     * do nothing, it's a view
     */
    public function clearFrontendMessages()
    {
    }
}
