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
use QUI\ERP\Output\Output as ERPOutput;
use QUI\ERP\Shipping\Api\ShippingInterface;

use function array_pop;
use function class_exists;
use function date;
use function dirname;
use function file_get_contents;
use function get_class;
use function strtotime;

/**
 * Class OrderView
 *
 * @package QUI\ERP\Order
 */
class OrderView extends QUI\QDOM implements OrderInterface
{
    /**
     * @var string
     */
    protected string $prefix;

    /**
     * @var Order
     */
    protected Order $Order;

    /**
     * @var ArticleList
     */
    protected ArticleList $Articles;

    /**
     * OrderView constructor.
     *
     * @param Order $Order
     */
    public function __construct(Order $Order)
    {
        $this->Order = $Order;
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
     * @return string
     */
    public function getPrefixedId(): string
    {
        return $this->getId();
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
     * @param null|QUI\Locale $Locale
     * @return mixed
     */
    public function getDate(QUI\Locale $Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $date = $this->Order->getCreateDate();
        $Formatter = $Locale->getDateFormatter();

        return $Formatter->format(strtotime($date));
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
     * @return string
     */
    public function getInvoiceType(): string
    {
        return $this->Order->getInvoiceType();
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

    //region preview

    /**
     * Get type string used for Order output (i.e. PDF / print)
     *
     * @return string
     */
    protected function getOutputType(): string
    {
        return 'Order';
    }

    /**
     * Return preview HTML
     * Like HTML or PDF with extra stylesheets to preview the view in DIN A4
     *
     * @return string
     */
    public function previewHTML(): string
    {
        try {
            $previewHtml = ERPOutput::getDocumentHtml(
                $this->Order->getCleanId(),
                $this->getOutputType(),
                null,
                null,
                null,
                true
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $previewHtml = '';
        }

        QUI::getLocale()->resetCurrent();

        return $previewHtml;
    }

    /**
     * return string
     */
    public function previewOnlyArticles(): string
    {
        try {
            $output = '';
            $output .= '<style>';
            $output .= file_get_contents(dirname(__FILE__) . '/Utils/Template.Articles.Preview.css');
            $output .= '</style>';
            $output .= $this->getArticles()->toHTML();

            $previewHtml = $output;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $previewHtml = '';
        }

        QUI::getLocale()->resetCurrent();

        return $previewHtml;
    }

    /**
     * Output the invoice as HTML
     *
     * @return string
     */
    public function toHTML(): string
    {
        try {
            return QUI\ERP\Output\Output::getDocumentHtml($this->Order->getCleanId(), $this->getOutputType());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return '';
    }

    /**
     * Get text descriping the transaction
     *
     * This may be a payment date or a "successfull payment" text, depening on the
     * existing invoice transactions.
     *
     * @return string
     * @throws \QUI\ERP\Accounting\Payments\Exception
     */
    public function getTransactionText(): string
    {
        if (!$this->Order->getPayment()) {
            return '';
        }

        $PaymentType = $this->Order->getPayment()->getPaymentType();

        if (
            class_exists('QUI\ERP\Accounting\Payments\Methods\AdvancePayment\Payment')
            && get_class($PaymentType) === QUI\ERP\Accounting\Payments\Methods\AdvancePayment\Payment::class
        ) {
            return '';
        }

        try {
            $Locale = QUI::getLocale();

            if ($this->Order->getCustomer()) {
                $Locale = $this->Order->getCustomer()->getLocale();
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return '';
        }

        // Temporary invoice (draft)
        $Transactions = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();
        $transactions = $Transactions->getTransactionsByHash($this->Order->getHash());

        if (empty($transactions)) {
            // Time for payment text
            $Formatter = $Locale->getDateFormatter();
            $timeForPayment = $this->Order->getAttribute('time_for_payment');

            // temporary invoice, the time for payment are days
            $timeForPayment = strtotime($timeForPayment);

            if (date('Y-m-d') === date('Y-m-d', $timeForPayment)) {
                $timeForPayment = $Locale->get('quiqqer/order', 'additional.order.text.timeForPayment.0');
            } else {
                $timeForPayment = $Formatter->format($timeForPayment);
            }

            return $Locale->get(
                'quiqqer/order',
                'additional.order.text.timeForPayment.inclVar',
                [
                    'timeForPayment' => $timeForPayment
                ]
            );
        }

        /* @var $Transaction QUI\ERP\Accounting\Payments\Transactions\Transaction */
        $Transaction = array_pop($transactions);
        $Payment = $Transaction->getPayment(); // payment method
        $PaymentType = $this->getPayment()->getPaymentType(); // payment method

        $payment = $Payment->getTitle();
        $Formatter = $Locale->getDateFormatter();

        if (get_class($PaymentType) === $Payment->getClass()) {
            $payment = $PaymentType->getTitle($Locale);
        }

        return $Locale->get('quiqqer/order', 'order.view.payment.transaction.text', [
            'date' => $Formatter->format(strtotime($Transaction->getDate())),
            'payment' => $payment
        ]);
    }

    /**
     * Output the order as PDF Document
     *
     * @return QUI\HtmlToPdf\Document
     *
     * @throws QUI\Exception
     */
    public function toPDF(): QUI\HtmlToPdf\Document
    {
        return QUI\ERP\Output\Output::getDocumentPdf(
            $this->Order->getCleanId(),
            $this->getOutputType()
        );
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
     * do nothing, it's a view
     */
    protected function saveFrontendMessages()
    {
    }

    /**
     * do nothing, it's a view
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
