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
     */
    const STATUS_CREATED = 0;

    /**
     * Order is posted (Invoice created)
     * Bestellung ist gebucht (Invoice erstellt)
     */
    const STATUS_POSTED = 1; // Bestellung ist gebucht (Invoice erstellt)


    const STATUS_STORNO = 2; // Bestellung ist storniert

    /**
     * order id
     *
     * @var integer
     */
    protected $id;

    /**
     * @var int
     */
    protected $status;

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
    protected $customer = array();

    /**
     * @var array
     */
    protected $addressInvoice = array();

    /**
     * @var array
     */
    protected $addressDelivery = array();

    /**
     * @var array
     */
    protected $articles = array();

    /**
     * @var array
     */
    protected $data;

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
     * Order constructor.
     *
     * @param array $data
     *
     * @throws Exception
     * @throws QUI\ERP\Exception
     */
    public function __construct($data = array())
    {
        $needles = Factory::getInstance()->getOrderConstructNeedles();

        foreach ($needles as $needle) {
            if (!isset($data[$needle]) && $data[$needle] !== null) {
                throw new Exception(array(
                    'quiqqer/order',
                    'exception.order.construct.needle.missing',
                    array('needle' => $needle)
                ));
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

            try {
                $this->setCustomer($this->customer);
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
        $this->paymentId = $data['payment_id'];

        $this->setAttributes(array(
            'paid_status' => (int)$data['paid_status'],
            'paid_data'   => json_decode($data['paid_data'], true),
            'paid_date'   => $data['paid_date']
        ));
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
        $paymentId = '';
        $Payment   = $this->getPayment();

        if ($Payment) {
            $paymentId = $Payment->getId();
        }

        return array(
            'id'        => $this->id,
            'invoiceId' => $this->invoiceId,
            'hash'      => $this->hash,
            'cDate'     => $this->cDate,
            'data'      => $this->data,

            'customerId' => $this->customerId,
            'customer'   => $this->getCustomer()->getAttributes(),

            'comments'        => $this->getComments()->toArray(),
            'articles'        => $this->getArticles()->toArray(),
            'addressDelivery' => $this->getDeliveryAddress()->getAttributes(),
            'addressInvoice'  => $this->getInvoiceAddress()->getAttributes(),
            'paymentId'       => $paymentId
        );
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
     * Set the invoice address
     *
     * @param array|QUI\ERP\Address $address
     */
    public function setInvoiceAddress($address)
    {
        if ($address instanceof QUI\ERP\Address) {
            $this->addressInvoice = $address->getAttributes();

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
        $fields = array_flip(array(
            'id',
            'salutation',
            'firstname',
            'lastname',
            'street_no',
            'zip',
            'city',
            'country',
            'company'
        ));

        $result = array();

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

        return array(
            'paidData' => $this->getAttribute('paid_data'),
            'paidDate' => $this->getAttribute('paid_date'),
            'paid'     => $this->getAttribute('paid'),
            'toPay'    => $this->getAttribute('toPay')
        );
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
                array(
                    'order'   => $this->getId(),
                    'payment' => $paymentId
                )
            );
        }

        $this->paymentId     = $Payment->getId();
        $this->paymentMethod = $Payment->getType();
    }

//    /**
//     * @param $amount
//     * @param PaymentsInterface $PaymentMethod
//     * @param bool $date
//     * @param null $PermissionUser
//     *
//     * @deprecated use transaction oder eine tx anlegen
//     */
//    public function addPayment(
//        $amount,
//        PaymentsInterface $PaymentMethod,
//        $date = false,
//        $PermissionUser = null
//    ) {
//        Permission::checkPermission(
//            'quiqqer.order.addPayment',
//            $PermissionUser
//        );
//
//        if ($this->getAttribute('paid_status') == self::PAYMENT_STATUS_PAID ||
//            $this->getAttribute('paid_status') == self::PAYMENT_STATUS_CANCELED
//        ) {
//            return;
//        }
//
//        QUI::getEvents()->fireEvent(
//            'quiqqerOrderAddPaymentBegin',
//            array($this, $amount, $PaymentMethod, $date)
//        );
//
//        $User     = QUI::getUserBySession();
//        $paidData = $this->getAttribute('paid_data');
//        $amount   = Price::validatePrice($amount);
//
//        if (!$amount) {
//            return;
//        }
//
//        if (!is_array($paidData)) {
//            $paidData = json_decode($paidData, true);
//        }
//
//        if (!is_array($paidData)) {
//            $paidData = array();
//        }
//
//
//        if ($date === false) {
//            $date = time();
//        }
//
//        $isValidTimeStamp = function ($timestamp) {
//            return ((string)(int)$timestamp === $timestamp)
//                   && ($timestamp <= PHP_INT_MAX)
//                   && ($timestamp >= ~PHP_INT_MAX);
//        };
//
//        if ($isValidTimeStamp($date) === false) {
//            $date = strtotime($date);
//
//            if ($isValidTimeStamp($date) === false) {
//                $date = time();
//            }
//        }
//
//        $paidData[] = array(
//            'amount'  => $amount,
//            'payment' => $PaymentMethod->getName(),
//            'date'    => $date
//        );
//
//        $this->setAttribute('paid_data', json_encode($paidData));
//        $this->setAttribute('paid_date', $date);
//
//        // calculations
//        $this->Articles->calc();
//        $listCalculations = $this->Articles->getCalculations();
//
//        $this->setAttributes(array(
//            'currency_data' => json_encode($listCalculations['currencyData']),
//            'nettosum'      => $listCalculations['nettoSum'],
//            'subsum'        => $listCalculations['subSum'],
//            'sum'           => $listCalculations['sum'],
//            'vat_array'     => json_encode($listCalculations['vatArray'])
//        ));
//
//
//        $this->addHistory(
//            QUI::getLocale()->get(
//                'quiqqer/order',
//                'history.message.addPayment',
//                array(
//                    'username' => $User->getName(),
//                    'uid'      => $User->getId(),
//                    'payment'  => $PaymentMethod->getTitle()
//                )
//            )
//        );
//
//        QUI::getEvents()->fireEvent(
//            'quiqqerOrderAddPayment',
//            array($this, $amount, $PaymentMethod, $date)
//        );
//
//        $this->calculatePayments();
//
//        QUI::getEvents()->fireEvent(
//            'quiqqerOrderAddPaymentEnd',
//            array($this, $amount, $PaymentMethod, $date)
//        );
//    }

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
            return;
        }

        QUI\ERP\Debug::getInstance()->log('Order:: add transaction start');

        $User     = QUI::getUserBySession();
        $paidData = $this->getAttribute('paid_data');
        $amount   = Price::validatePrice($Transaction->getAmount());
        $date     = $Transaction->getDate();

        QUI::getEvents()->fireEvent(
            'quiqqerOrderAddTransactionBegin',
            array($this, $amount, $Transaction, $date)
        );


        if (!$amount) {
            return;
        }

        if (!is_array($paidData)) {
            $paidData = json_decode($paidData, true);
        }

        if (!is_array($paidData)) {
            $paidData = array();
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

        $this->setAttributes(array(
            'currency_data' => json_encode($listCalculations['currencyData']),
            'nettosum'      => $listCalculations['nettoSum'],
            'subsum'        => $listCalculations['subSum'],
            'sum'           => $listCalculations['sum'],
            'vat_array'     => json_encode($listCalculations['vatArray'])
        ));


        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/order',
                'history.message.addTransaction',
                array(
                    'username' => $User->getName(),
                    'uid'      => $User->getId(),
                    'txid'     => $Transaction->getTxId()
                )
            )
        );

        QUI::getEvents()->fireEvent(
            'addTransaction',
            array($this, $amount, $Transaction, $date)
        );

        $this->calculatePayments();

        QUI::getEvents()->fireEvent(
            'quiqqerOrderAddTransactionEnd',
            array($this, $amount, $Transaction, $date)
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
        $this->addressInvoice = array();
    }

    /**
     * Remove the invoice address from the order
     */
    public function clearAddressDelivery()
    {
        $this->addressDelivery = array();
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
}
