<?php

namespace QUI\ERP\Order;

use DateTime;
use QUI;
use QUI\Database\Exception;
use QUI\ERP\Accounting\Payments\Transactions\IncomingPayments\PaymentReceiverInterface;
use QUI\ERP\Accounting\Payments\Types\PaymentInterface;
use QUI\ERP\Address;
use QUI\ERP\Currency\Currency;
use QUI\Locale;

use function date_create;
use function is_string;

/**
 * Class PaymentReceiver
 *
 * Payment receiver provider for quiqqer/order
 */
class PaymentReceiver implements PaymentReceiverInterface
{
    /**
     * @var ?AbstractOrder
     */
    protected AbstractOrder | null $Order = null;

    /**
     * Get entity type descriptor
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'Order';
    }

    /**
     * Get entity type title
     *
     * @param Locale|null $Locale $Locale (optional) - If omitted use \QUI::getLocale()
     * @return string
     */
    public static function getTypeTitle(null | Locale $Locale = null): string
    {
        if (empty($Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/order', 'PaymentReceiver.Order.title');
    }

    /**
     * PaymentReceiverInterface constructor.
     *
     * @param string|int $id - Payment receiver entity ID
     *
     * @throws Exception
     * @throws Exception
     * @throws QUI\ERP\Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function __construct($id)
    {
        if (is_string($id)) {
            $result = QUI::getDataBase()->fetch([
                'select' => ['id'],
                'from' => Handler::getInstance()->table(),
                'where' => [
                    'id_str' => $id
                ]
            ]);

            if (!empty($result)) {
                $id = $result[0]['id'];
            }
        }

        try {
            $this->Order = Handler::getInstance()->get($id);
        } catch (\Exception) {
            $this->Order = Handler::getInstance()->getOrderByHash($id);
        }

        $this->Order->calculatePayments();
    }

    /**
     * Get payment address of the debtor (e.g. customer)
     *
     * @return Address|false
     */
    public function getDebtorAddress(): bool | Address
    {
        return $this->Order->getCustomer()->getStandardAddress();
    }

    /**
     * Get full document no
     *
     * @return string
     */
    public function getDocumentNo(): string
    {
        return $this->Order->getPrefixedNumber();
    }

    /**
     * Get the unique recipient no. of the debtor (e.g. customer no.)
     *
     * @return string
     */
    public function getDebtorNo(): string
    {
        return $this->Order->getCustomer()->getAttribute('customerNo');
    }

    /**
     * Get date of the document
     *
     * @return DateTime
     */
    public function getDate(): DateTime
    {
        $date = $this->Order->getAttribute('date');

        if (!empty($date)) {
            $Date = date_create($date);

            if ($Date) {
                return $Date;
            }
        }

        return date_create();
    }

    /**
     * Get entity due date (if applicable)
     *
     * @return DateTime|false
     */
    public function getDueDate(): DateTime | bool
    {
        $date = $this->Order->getAttribute('payment_time');

        if (!empty($date)) {
            $Date = date_create($date);

            if ($Date) {
                return $Date;
            }
        }

        return false;
    }

    /**
     * @return Currency
     */
    public function getCurrency(): Currency
    {
        return $this->Order->getCurrency();
    }

    /**
     * Get the total amount of the document
     *
     * @return float
     */
    public function getAmountTotal(): float
    {
        return $this->Order->getAttribute('sum');
    }

    /**
     * Get the total amount still open of the document
     *
     * @return float
     */
    public function getAmountOpen(): float
    {
        return $this->Order->getAttribute('toPay');
    }

    /**
     * Get the total amount already paid of the document
     *
     * @return float
     */
    public function getAmountPaid(): float
    {
        return $this->Order->getAttribute('paid');
    }

    /**
     * Get payment method
     *
     * @return PaymentInterface|false
     */
    public function getPaymentMethod(): bool | PaymentInterface
    {
        try {
            return QUI\ERP\Accounting\Payments\Payments::getInstance()->getPayment(
                $this->Order->getAttribute('payment_id')
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            return false;
        }
    }

    /**
     * Get the current payment status of the ERP object
     *
     * @return int - One of \QUI\ERP\Constants::PAYMENT_STATUS_*
     */
    public function getPaymentStatus(): int
    {
        return (int)$this->Order->getAttribute('paid_status');
    }
}
