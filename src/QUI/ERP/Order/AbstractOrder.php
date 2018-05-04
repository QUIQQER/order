<?php

/**
 * This file contains QUI\ERP\Order\AbstractOrder
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Money\Price;

use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Accounting\Payments\Transactions\Handler as TransactionHandler;

/**
 * Class AbstractOrder
 *
 * Main parent class for order classes
 * - Order
 * - OrderProcess
 *
 * @package QUI\ERP\Order
 */
abstract class AbstractOrder extends QUI\QDOM
{
    const PAYMENT_STATUS_OPEN = 0;
    const PAYMENT_STATUS_PAID = 1;
    const PAYMENT_STATUS_PART = 2;
    const PAYMENT_STATUS_ERROR = 4;
    const PAYMENT_STATUS_CANCELED = 5;
    const PAYMENT_STATUS_DEBIT = 11;

    /**
     * Order is only created
     *
     * @deprecated
     */
    const STATUS_CREATED = 0;

    /**
     * Order is posted (Invoice created)
     * Bestellung ist gebucht (Invoice erstellt)
     *
     * @deprecated
     */
    const STATUS_POSTED = 1; // Bestellung ist gebucht (Invoice erstellt)

    /**
     * @deprecated
     */
    const STATUS_STORNO = 2; // Bestellung ist storniert

    /**
     * order id
     *
     * @var integer
     */
    protected $id;

    /**
     * @var string
     */
    protected $idPrefix = null;

    /**
     * @var int
     */
    protected $status = 0;

    /**
     * @var int
     */
    protected $successful;

    /**
     * invoice ID
     *
     * @var integer
     */
    protected $invoiceId = false;

    /**
     * @var integer
     */
    protected $customerId;

    /**
     * @var array
     */
    protected $customer = [];

    /**
     * @var array
     */
    protected $addressInvoice = [];

    /**
     * @var array
     */
    protected $addressDelivery = [];

    /**
     * @var array
     */
    protected $articles = [];

    /**
     * @var array
     */
    protected $data;
    /**
     * @var array
     */
    protected $paymentData = [];

    /**
     * @var string
     */
    protected $hash;

    /**
     * @var integer
     */
    protected $cDate;

    /**
     * Create user id
     *
     * @var integer
     */
    protected $cUser;

    /**
     * @var ArticleList
     */
    protected $Articles = null;

    /**
     * @var QUI\ERP\Comments
     */
    protected $Comments = null;

    /**
     * @var QUI\ERP\Comments
     */
    protected $History = null;

    /**
     * @var null|QUI\ERP\User
     */
    protected $Customer = null;

    // payments

    /**
     * @var integer
     */
    protected $paymentId;

    /**
     * @var integer
     */
    protected $paymentMethod;

    /**
     * @var bool
     */
    protected $statusChanged = false;

    /**
     * Order constructor.
     *
     * @param array $data
     *
     * @throws Exception
     * @throws QUI\ERP\Exception
     */
    public function __construct($data = [])
    {
        $needles = Factory::getInstance()->getOrderConstructNeedles();

        foreach ($needles as $needle) {
            if (!isset($data[$needle]) && $data[$needle] !== null) {
                throw new Exception([
                    'quiqqer/order',
                    'exception.order.construct.needle.missing',
                    ['needle' => $needle]
                ]);
            }
        }

        $this->id    = (int)$data['id'];
        $this->hash  = $data['hash'];
        $this->cDate = $data['c_date'];
        $this->cUser = (int)$data['c_user'];

        $this->setDataBaseData($data);
    }

    /**
     * Set the db data to the order object
     *
     * @param array $data
     *
     * @throws QUI\ERP\Exception
     */
    protected function setDataBaseData(array $data)
    {
        $this->invoiceId = $data['invoice_id'];

        $this->addressDelivery = json_decode($data['addressDelivery'], true);
        $this->addressInvoice  = json_decode($data['addressInvoice'], true);
        $this->data            = json_decode($data['data'], true);

        // user
        $this->customerId = (int)$data['customerId'];

        if (isset($data['customer'])) {
            $this->customer = json_decode($data['customer'], true);

            if (!isset($this->customer['id'])) {
                $this->customer['id'] = $this->customerId;
            }

            $customerData = $this->customer;

            if (!isset($customerData['address'])) {
                $customerData['address'] = $this->addressInvoice;
            }

            try {
                $this->setCustomer($customerData);
            } catch (QUi\Exception $Exception) {
                QUI\System\Log::writeRecursive($this->customer);
                QUI\System\Log::addWarning($Exception->getMessage());
            }
        }


        // articles
        $this->Articles = new ArticleList();

        if (isset($data['articles'])) {
            $articles = json_decode($data['articles'], true);

            if ($articles) {
                try {
                    $this->Articles = new ArticleList($articles);
                } catch (QUI\ERP\Exception $Exception) {
                    QUI\System\Log::addError($Exception->getMessage());
                }
            }
        }

        // comments
        $this->Comments = new QUI\ERP\Comments();

        if (isset($data['comments'])) {
            $this->Comments = QUI\ERP\Comments::unserialize($data['comments']);
        }

        // history
        $this->History = new QUI\ERP\Comments();

        if (isset($data['history'])) {
            $this->History = QUI\ERP\Comments::unserialize($data['history']);
        }

        // payment
        $this->paymentId  = $data['payment_id'];
        $this->successful = (int)$data['successful'];

        $this->setAttributes([
            'paid_status' => (int)$data['paid_status'],
            'paid_data'   => json_decode($data['paid_data'], true),
            'paid_date'   => $data['paid_date']
        ]);

        if (isset($data['payment_data'])) {
            $paymentData = QUI\Security\Encryption::decrypt($data['payment_data']);
            $paymentData = json_decode($paymentData, true);

            $this->paymentData = $paymentData;
        }
    }

    /**
     * @throws QUI\Exception
     * @throws QUI\ExceptionStack
     */
    public function setSuccessfulStatus()
    {
        if ($this->successful === 1) {
            return;
        }

        // @todo create invoice

        QUI::getEvents()->fireEvent('quiqqerOrderSuccessful', [$this]);

        $this->successful = 1;
        $this->update();
    }

    //region API

    /**
     * Refresh the order data
     * fetch the data from the database
     */
    abstract public function refresh();

    /**
     * Updates the order
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional, permission user, default = session user
     */
    abstract public function update($PermissionUser = null);

    /**
     * Delete the order
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional, permission user, default = session user
     */
    abstract public function delete($PermissionUser = null);

    /**
     * Is the order posted / submitted
     *
     * @return bool
     */
    abstract public function isPosted();

    //endregion

    //region getter

    /**
     * Return the order as an array
     *
     * @return array
     */
    public function toArray()
    {
        $articles  = '';
        $paymentId = '';

        $Payment = $this->getPayment();

        if ($Payment) {
            $paymentId = $Payment->getId();
        }

        try {
            $articles = $this->getArticles()->toArray();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return [
            'id'        => $this->id,
            'invoiceId' => $this->invoiceId,
            'hash'      => $this->hash,
            'cDate'     => $this->cDate,
            'cUser'     => $this->cUser,
            'cUsername' => $this->getCreateUser()->getName(),
            'data'      => $this->data,

            'customerId' => $this->customerId,
            'customer'   => $this->getCustomer()->getAttributes(),

            'comments'           => $this->getComments()->toArray(),
            'articles'           => $articles,
            'hasDeliveryAddress' => $this->hasDeliveryAddress(),
            'addressDelivery'    => $this->getDeliveryAddress()->getAttributes(),
            'addressInvoice'     => $this->getInvoiceAddress()->getAttributes(),
            'paymentId'          => $paymentId
        ];
    }

    /**
     * Return the order id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return array|bool|string
     */
    public function getIdPrefix()
    {
        if ($this->idPrefix !== null) {
            return $this->idPrefix;
        }

        return QUI\ERP\Order\Utils\Utils::getOrderPrefix();
    }

    /**
     * Return the real order id for the customer
     *
     * @return string
     */
    public function getPrefixedId()
    {
        return $this->getIdPrefix().$this->getId();
    }

    /**
     * @return QUI\ERP\Accounting\Calculations
     *
     * @throws QUI\ERP\Exception
     * @throws QUI\Exception
     */
    public function getPriceCalculation()
    {
        $this->Articles->calc();

        $Calculations = new QUI\ERP\Accounting\Calculations(
            $this->Articles->getCalculations(),
            $this->Articles->getArticles()
        );

        return $Calculations;
    }

    /**
     * Is the order successful
     * -> Ist der Bestellablauf erfolgreich abgeschlossen
     *
     * @return int
     */
    public function isSuccessful()
    {
        return $this->successful;
    }

    /**
     * Return invoice address
     *
     * @return QUI\ERP\Address
     */
    public function getInvoiceAddress()
    {
        return new QUI\ERP\Address($this->addressInvoice, $this->getCustomer());
    }

    /**
     * Return delivery address
     *
     * @return QUI\ERP\Address
     */
    public function getDeliveryAddress()
    {
        if (empty($this->addressDelivery)) {
            return $this->getInvoiceAddress();
        }

        return new QUI\ERP\Address($this->addressDelivery, $this->getCustomer());
    }

    /**
     * Return the order articles list
     *
     * @return ArticleList
     */
    public function getArticles()
    {
        $this->Articles->setUser($this->getCustomer());
        $this->Articles->setCurrency($this->getCurrency());
        $this->Articles->calc();

        return $this->Articles;
    }

    /**
     * Return the order create date
     *
     * @return integer
     */
    public function getCreateDate()
    {
        return $this->cDate;
    }

    /**
     * Return the order create date
     *
     * @return QUI\Interfaces\Users\User
     */
    public function getCreateUser()
    {
        try {
            return QUI::getUsers()->get($this->cUser);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return QUI::getUsers()->getSystemUser();
    }

    /**
     * Return extra data array
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Return a data entry
     *
     * @param string $key
     * @return mixed|null
     */
    public function getDataEntry($key)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        return null;
    }

    /**
     * Return the hash
     *
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * Return the customer of the order
     *
     * @return QUI\ERP\User
     */
    public function getCustomer()
    {
        $Nobody = QUI\ERP\User::convertUserToErpUser(QUI::getUsers()->getNobody());

        if (!$this->customerId && !$this->Customer) {
            return $Nobody;
        }

        if ($this->Customer) {
            return $this->Customer;
        }

        if ($this->customer) {
            try {
                $this->setCustomer($this->customer);

                return $this->Customer;
            } catch (QUi\Exception $Exception) {
                QUI\System\Log::writeRecursive($this->customer);
                QUI\System\Log::addWarning($Exception->getMessage());
            }
        }

        try {
            $User     = QUI::getUsers()->get($this->customerId);
            $Customer = QUI\ERP\User::convertUserToErpUser($User);

            $this->Customer = $Customer;

            return $this->Customer;
        } catch (QUI\Exception $Exception) {
        }

        return $Nobody;
    }

    /**
     * Return the currency of the order
     * - At the moment its the default currency of the system
     * - If we want to have different currencies, this can be implemented here
     *
     * @return QUI\ERP\Currency\Currency
     */
    public function getCurrency()
    {
        return QUI\ERP\Defaults::getCurrency();
    }

    /**
     * Has the order a delivery address?
     *
     * @return bool
     */
    public function hasDeliveryAddress()
    {
        return !empty($this->addressDelivery);
    }

    //endregion

    //region setter

    /**
     * Set the delivery address
     *
     * @param array|QUI\ERP\Address $address
     */
    public function setDeliveryAddress($address)
    {
        if ($address instanceof QUI\ERP\Address) {
            $this->addressDelivery = $address->getAttributes();

            return;
        }

        if (is_array($address)) {
            $this->addressDelivery = $this->parseAddressData($address);
        }
    }

    /**
     * Clear up / remove the deliver address from the order
     */
    public function removeDeliveryAddress()
    {
        $this->addressDelivery = [];
    }

    /**
     * Set the invoice address
     *
     * @param array|QUI\ERP\Address|QUI\Users\Address $address
     */
    public function setInvoiceAddress($address)
    {
        if ($address instanceof QUI\ERP\Address ||
            $address instanceof QUI\Users\Address) {
            $this->addressInvoice       = $address->getAttributes();
            $this->addressInvoice['id'] = $address->getId();

            return;
        }

        if (is_array($address)) {
            $this->addressInvoice = $this->parseAddressData($address);
        }
    }

    /**
     * @param array $address
     * @return array
     */
    protected function parseAddressData(array $address)
    {
        $fields = array_flip([
            'id',
            'salutation',
            'firstname',
            'lastname',
            'street_no',
            'zip',
            'city',
            'country',
            'company'
        ]);

        $result = [];

        foreach ($address as $entry => $value) {
            if (isset($fields[$entry])) {
                $result[$entry] = $value;
            }
        }

        return $result;
    }

    /**
     * Set extra data to the order
     *
     * @param string $key
     * @param string|integer|mixed $value
     */
    public function setData($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Set an customer to the order
     *
     * @param array|QUI\ERP\User|QUI\Interfaces\Users\User $User
     * @throws QUI\ERP\Exception
     */
    public function setCustomer($User)
    {
        if (empty($User)) {
            return;
        }

        if (is_array($User)) {
            $missing = QUI\ERP\User::getMissingAttributes($User);

            // if something is missing
            if (!empty($missing)) {
                try {
                    $Customer = QUI::getUsers()->get($User['id']);

                    foreach ($missing as $missingAttribute) {
                        if ($missingAttribute === 'username') {
                            $User[$missingAttribute] = $Customer->getUsername();
                            continue;
                        }

                        if ($missingAttribute === 'isCompany') {
                            $User[$missingAttribute] = $Customer->isCompany();
                            continue;
                        }

                        $User[$missingAttribute] = $Customer->getAttribute($missingAttribute);
                    }
                } catch (QUI\Exception $Exception) {
                    // we have a problem, we cant set the user
                }
            }

            $this->Customer   = new QUI\ERP\User($User);
            $this->customerId = $this->Customer->getId();

            return;
        }

        if ($User instanceof QUI\ERP\User) {
            $this->Customer   = $User;
            $this->customerId = $User->getId();

            return;
        }

        if ($User instanceof QUI\Interfaces\Users\User) {
            $this->Customer   = QUI\ERP\User::convertUserToErpUser($User);
            $this->customerId = $this->Customer->getId();
        }
    }

    //endregion

    //region payments

    /**
     * Return the payment
     *
     * @return null|QUI\ERP\Accounting\Payments\Types\Payment
     */
    public function getPayment()
    {
        if ($this->paymentId === null) {
            return null;
        }

        $Payments = Payments::getInstance();

        try {
            return $Payments->getPayment($this->paymentId);
        } catch (QUI\Exception $Exception) {
        }

        return null;
    }

    /**
     * Return the payment paid status information
     * - How many has already been paid
     * - How many must be paid
     *
     * @return array
     *
     * @throws QUI\ERP\Exception
     */
    public function getPaidStatusInformation()
    {
        QUI\ERP\Accounting\Calc::calculatePayments($this);

        return [
            'paidData' => $this->getAttribute('paid_data'),
            'paidDate' => $this->getAttribute('paid_date'),
            'paid'     => $this->getAttribute('paid'),
            'toPay'    => $this->getAttribute('toPay')
        ];
    }

    /**
     * Is the order already paid?
     *
     * @return bool
     */
    public function isPaid()
    {
        return $this->getAttribute('paid_status') == self::PAYMENT_STATUS_PAID;
    }

    /**
     * Set the payment method to the order
     *
     * @param string|integer $paymentId
     * @throws Exception
     *
     * @todo Payment->canBeUsed() noch implementieren
     */
    public function setPayment($paymentId)
    {
        $Payments = Payments::getInstance();

        try {
            $Payment = $Payments->getPayment($paymentId);
        } catch (QUI\ERP\Accounting\Payments\Exception $Exception) {
            throw new Exception(
                $Exception->getMessage(),
                $Exception->getCode(),
                [
                    'order'   => $this->getId(),
                    'payment' => $paymentId
                ]
            );
        }

        $this->paymentId     = $Payment->getId();
        $this->paymentMethod = $Payment->getType();
    }

    /**
     * Clear the payment method of the order
     */
    public function clearPayment()
    {
        $this->paymentId     = null;
        $this->paymentMethod = null;
    }

    /**
     * Set extra payment data to the order
     * This data is stored encrypted in the database
     *
     * @param string $key
     * @param string|integer|mixed $value
     */
    public function setPaymentData($key, $value)
    {
        $this->paymentData[$key] = $value;
    }

    /**
     * Return the complete payment data array (decrypted)
     *
     * @return array
     */
    public function getPaymentData()
    {
        return $this->paymentData;
    }

    /**
     * Return an entry in the payment data (decrypted)
     *
     * @param $key
     * @return mixed|null
     */
    public function getPaymentDataEntry($key)
    {
        if (isset($this->paymentData[$key])) {
            return $this->paymentData[$key];
        }

        return null;
    }

    /**
     * @param Transaction $Transaction
     *
     * @throws QUI\Exception
     */
    public function addTransaction(Transaction $Transaction)
    {
        QUI\ERP\Debug::getInstance()->log('Order:: add transaction');

        if ($this->getHash() !== $Transaction->getHash()) {
            return;
        }

        if ($this->getAttribute('paid_status') == self::PAYMENT_STATUS_PAID ||
            $this->getAttribute('paid_status') == self::PAYMENT_STATUS_CANCELED
        ) {
            $this->calculatePayments();

            return;
        }

        QUI\ERP\Debug::getInstance()->log('Order:: add transaction start');

        $User     = QUI::getUserBySession();
        $paidData = $this->getAttribute('paid_data');
        $amount   = Price::validatePrice($Transaction->getAmount());
        $date     = $Transaction->getDate();

        QUI::getEvents()->fireEvent(
            'quiqqerOrderAddTransactionBegin',
            [$this, $amount, $Transaction, $date]
        );


        if (!$amount) {
            return;
        }

        if (!is_array($paidData)) {
            $paidData = json_decode($paidData, true);
        }

        if (!is_array($paidData)) {
            $paidData = [];
        }

        function isTxAlreadyAdded($txid, $paidData)
        {
            foreach ($paidData as $paidEntry) {
                if (!isset($paidEntry['txid'])) {
                    continue;
                }

                if ($paidEntry['txid'] == $txid) {
                    return true;
                }
            }

            return false;
        }

        // already added
        if (isTxAlreadyAdded($Transaction->getTxId(), $paidData)) {
            return;
        }


        $isValidTimeStamp = function ($timestamp) {
            return ((string)(int)$timestamp === $timestamp)
                   && ($timestamp <= PHP_INT_MAX)
                   && ($timestamp >= ~PHP_INT_MAX);
        };

        if ($isValidTimeStamp($date) === false) {
            $date = strtotime($date);

            if ($isValidTimeStamp($date) === false) {
                $date = time();
            }
        }


        $this->setAttribute('paid_date', $date);

        // calculations
        $this->Articles->calc();
        $listCalculations = $this->Articles->getCalculations();

        $this->setAttributes([
            'currency_data' => json_encode($listCalculations['currencyData']),
            'nettosum'      => $listCalculations['nettoSum'],
            'subsum'        => $listCalculations['subSum'],
            'sum'           => $listCalculations['sum'],
            'vat_array'     => json_encode($listCalculations['vatArray'])
        ]);


        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/order',
                'history.message.addTransaction',
                [
                    'username' => $User->getName(),
                    'uid'      => $User->getId(),
                    'txid'     => $Transaction->getTxId()
                ]
            )
        );

        QUI::getEvents()->fireEvent(
            'addTransaction',
            [$this, $amount, $Transaction, $date]
        );

        $this->calculatePayments();

        QUI::getEvents()->fireEvent(
            'quiqqerOrderAddTransactionEnd',
            [$this, $amount, $Transaction, $date]
        );
    }

    /**
     * Alias for getTransactions
     */
    public function getPayments()
    {
        return $this->getTransactions();
    }

    /**
     * Return all transactions related to the order
     *
     * @return array
     */
    public function getTransactions()
    {
        return TransactionHandler::getInstance()->getTransactionsByHash(
            $this->getHash()
        );
    }

    /**
     * @return mixed
     */
    abstract protected function calculatePayments();

    //endregion

    //region removing / deletion

    /**
     * Remove the invoice address from the order
     */
    public function clearAddressInvoice()
    {
        $this->addressInvoice = [];
    }

    /**
     * Remove the invoice address from the order
     */
    public function clearAddressDelivery()
    {
        $this->addressDelivery = [];
    }

    /**
     * Remove an extra data entry
     *
     * @param string $key
     */
    public function removeData($key)
    {
        if (isset($this->data[$key])) {
            unset($this->data[$key]);
        }
    }
    //endregion

    //region articles

    /**
     * Add an Product to the order
     *
     * @param QUI\ERP\Accounting\Article $Article
     */
    public function addArticle(QUI\ERP\Accounting\Article $Article)
    {
        $this->Articles->addArticle($Article);
    }

    /**
     * Remove an article by its index position
     *
     * @param integer $index
     */
    public function removeArticle($index)
    {
        $this->Articles->removeArticle($index);
    }

    /**
     * Replace an article at a specific position
     *
     * @param QUI\ERP\Accounting\Article $Article
     * @param integer $index
     */
    public function replaceArticle(QUI\ERP\Accounting\Article $Article, $index)
    {
        $this->Articles->replaceArticle($Article, $index);
    }

    /**
     * Clears the article list
     */
    public function clearArticles()
    {
        $this->Articles->clear();
    }

    /**
     * Return the length of the article list
     *
     * @return int
     */
    public function count()
    {
        return $this->Articles->count();
    }

    //endregion


    //region comments

    /**
     * Add a comment
     *
     * @param string $message
     */
    public function addComment($message)
    {
        $this->Comments->addComment($message);
    }

    /**
     * Return the comments
     *
     * @return null|QUI\ERP\Comments
     */
    public function getComments()
    {
        return $this->Comments;
    }

    //endregion


    //region history

    /**
     * Add a history entry
     *
     * @param string $message
     */
    public function addHistory($message)
    {
        $this->History->addComment($message);
    }

    /**
     * Return the history object
     *
     * @return null|QUI\ERP\Comments
     */
    public function getHistory()
    {
        return $this->History;
    }

    //endregion

    //region process status

    /**
     * Return the order status (processing status)
     * This status is the custom status of the order system
     * Ths status can vary
     *
     * @return QUI\ERP\Order\ProcessingStatus\Status
     *
     * @throws QUI\ERP\Order\ProcessingStatus\Exception
     */
    public function getProcessingStatus()
    {
        $Handler = QUI\ERP\Order\ProcessingStatus\Handler::getInstance();

        return $Handler->getProcessingStatus($this->status);
    }

    /**
     * Set a processing status to the order
     *
     * @param int|ProcessingStatus\Status $status
     */
    public function setProcessingStatus($status)
    {
        if ($status instanceof ProcessingStatus\Status) {
            $Status = $status;
        } else {
            try {
                $Handler = QUI\ERP\Order\ProcessingStatus\Handler::getInstance();
                $Status  = $Handler->getProcessingStatus($status);
            } catch (QUI\ERP\Order\ProcessingStatus\Exception $Exception) {
                QUI\System\Log::addWarning($Exception->getMessage());

                return;
            }
        }

        $this->status        = $Status->getId();
        $this->statusChanged = true;
    }

    //endregion
}
