<?php

/**
 * This file contains QUI\ERP\Order\AbstractOrder
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Payments\Payments;

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
abstract class AbstractOrder extends QUI\QDOM implements OrderInterface
{
    const PAYMENT_STATUS_OPEN = 0;
    const PAYMENT_STATUS_PAID = 1;
    const PAYMENT_STATUS_PART = 2;
    const PAYMENT_STATUS_ERROR = 4;
    const PAYMENT_STATUS_CANCELED = 5;
    const PAYMENT_STATUS_DEBIT = 11;
    const PAYMENT_STATUS_PLAN = 12;

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
     * @var QUI\ERP\Currency\Currency
     */
    protected $Currency = null;

    //shipping

    /**
     * @var integer|null
     */
    protected $shippingId = null;

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

        $this->idPrefix        = $data['id_prefix'];
        $this->addressDelivery = \json_decode($data['addressDelivery'], true);
        $this->addressInvoice  = \json_decode($data['addressInvoice'], true);
        $this->data            = \json_decode($data['data'], true);

        if (isset($data['status'])) {
            $this->status = $data['status'];
        }

        // user
        $this->customerId = (int)$data['customerId'];

        if (isset($data['customer'])) {
            $this->customer = \json_decode($data['customer'], true);

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

            if (isset($customerData['address']['id']) && $customerData['address']['id']) {
                $this->Customer->setAddress($this->getInvoiceAddress());
            } elseif (isset($customerData['quiqqer.erp.address'])) {
                try {
                    $User    = QUI::getUsers()->get($this->Customer->getId());
                    $Address = $User->getAddress($customerData['quiqqer.erp.address']);
                    $this->Customer->setAddress($Address);
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::writeDebugException($Exception);
                }
            }
        }


        // articles
        $this->Articles = new ArticleList();

        if (isset($data['articles'])) {
            $articles = \json_decode($data['articles'], true);

            if ($articles) {
                // clear vat for recalculation
                if (isset($articles['articles'])) {
                    foreach ($articles['articles'] as $key => $article) {
                        unset($articles['articles'][$key]['vat']);
                    }
                }

                try {
                    $this->Articles = new ArticleList($articles);
                } catch (QUI\ERP\Exception $Exception) {
                    QUI\System\Log::addError($Exception->getMessage());
                }
            }
        }

        $this->Articles->setUser($this->getCustomer());
        $this->Articles->calc();

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
            'paid_status'          => (int)$data['paid_status'],
            'paid_data'            => \json_decode($data['paid_data'], true),
            'paid_date'            => $data['paid_date'],
            'temporary_invoice_id' => $data['temporary_invoice_id'],
        ]);

        if (isset($data['payment_data'])) {
            $paymentData = QUI\Security\Encryption::decrypt($data['payment_data']);
            $paymentData = \json_decode($paymentData, true);

            $this->paymentData = $paymentData;
        }

        // shipping
        if (\is_numeric($data['shipping_id'])) {
            $this->shippingId = (int)$data['shipping_id'];
        }

        $shippingTypeData = $data['shipping_type_data'];
        $shippingTypeData = \json_decode($shippingTypeData, true);

        if (!\is_array($shippingTypeData)) {
            $shippingTypeData = [];
        }

        if (!empty($shippingTypeData)) {
        }

        // currency
        if (!empty($data['currency_data'])) {
            $currency = \json_decode($data['currency_data'], true);

            if (is_string($currency)) {
                $currency = \json_decode($currency, true);
            }

            if ($currency && isset($currency['code'])) {
                try {
                    $this->Currency = QUI\ERP\Currency\Handler::getCurrency($currency['code']);
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::addDebug($Exception->getMessage());
                }
            }
        }

        if ($this->Currency === null) {
            $this->Currency = QUI\ERP\Defaults::getCurrency();
        }

        $this->Articles->setCurrency($this->Currency);
    }

    /**
     * Set the successful status to the order
     *
     * @throws QUI\Exception
     * @throws QUI\ExceptionStack
     */
    public function setSuccessfulStatus()
    {
        if ($this->successful === 1) {
            return;
        }

        QUI::getEvents()->fireEvent('quiqqerOrderSuccessful', [$this]);

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/order',
                'order.history.set_successful'
            )
        );

        $this->successful = 1;
        $this->update();
    }

    //region API

    /**
     * Recalculate all article prices
     *
     * @param $Basket - optional
     *
     * @throws Basket\Exception
     * @throws QUI\ERP\Exception
     * @throws QUI\Exception
     */
    public function recalculate($Basket = null)
    {
        if ($Basket === null) {
            $Basket = new QUI\ERP\Order\Basket\BasketOrder(
                $this->getHash(),
                $this->getCustomer()
            );
        }

        $ArticleList = new ArticleList();
        $ArticleList->setUser($this->getCustomer());

        $ProductCalc = QUI\ERP\Products\Utils\Calc::getInstance();
        $ProductCalc->setUser($this->getCustomer());

        $Products = $Basket->getProducts();
        $Products->calc($ProductCalc);

        $products = $Products->getProducts();

        foreach ($products as $Product) {
            try {
                /* @var QUI\ERP\Order\Basket\Product $Product */
                $ArticleList->addArticle($Product->toArticle(null, false));
            } catch (QUI\Exception $Exception) {
                // @todo hinweis an benutzer, das artikel nicht mit aufgenommen werden konnte
            }
        }

        $Products->getPriceFactors()->clear();

        QUI::getEvents()->fireEvent(
            'quiqqerOrderBasketToOrder',
            [$Basket, $this, $Products]
        );

        $ArticleList->importPriceFactors(
            $Products->getPriceFactors()->toErpPriceFactorList()
        );

        $ArticleList->calc();

        $this->Articles = $ArticleList;
        $this->update();
    }

    /**
     * Clears the complete order
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional, permission user, default = session user
     */
    abstract public function clear($PermissionUser = null);

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
        $status    = '';
        $paymentId = '';

        $articles = $this->getArticles()->toArray();
        $Payment  = $this->getPayment();

        if ($Payment) {
            $paymentId = $Payment->getId();
        }

        try {
            $ProcessingStatus = $this->getProcessingStatus();
            $status           = $ProcessingStatus->getId();
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
            'paymentId'          => $paymentId,
            'status'             => $status
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
     * Return the order id
     * -> alias for getId(), this method is used in the calc classes
     *
     * @return integer
     */
    public function getCleanId()
    {
        return $this->getId();
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
        return $this->Currency;
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

        if (\is_array($address)) {
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

        if (\is_array($address)) {
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
     * Set the currency of the order
     *
     * @param QUI\ERP\Currency\Currency $Currency
     */
    public function setCurrency(QUI\ERP\Currency\Currency $Currency)
    {
        $this->Currency = $Currency;
        $this->Articles->setCurrency($Currency);
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

        if (\is_array($User)) {
            $missing = QUI\ERP\User::getMissingAttributes($User);

            // if something is missing
            if (!empty($missing)) {
                try {
                    $Customer = QUI::getUsers()->get($User['id']);

                    if (isset($User['address'])) {
                        $address = $User['address'];
                    }

                    foreach ($missing as $missingAttribute) {
                        if ($missingAttribute === 'username') {
                            $User[$missingAttribute] = $Customer->getUsername();
                            continue;
                        }

                        if ($missingAttribute === 'isCompany') {
                            $User[$missingAttribute] = $Customer->isCompany();
                            continue;
                        }

                        if (!empty($address[$missingAttribute])) {
                            $User[$missingAttribute] = $address[$missingAttribute];
                            continue;
                        }

                        $User[$missingAttribute] = $Customer->getAttribute($missingAttribute);
                    }
                } catch (QUI\Exception $Exception) {
                    // we have a problem, we cant set the user
                    // we need to fill the user data with empty values
                    $address = [];

                    if (isset($User['address'])) {
                        $address = $User['address'];
                    }

                    foreach ($missing as $missingAttribute) {
                        if ($missingAttribute === 'isCompany') {
                            if (!empty($address['company'])) {
                                $User[$missingAttribute] = 1;
                                continue;
                            }

                            $User[$missingAttribute] = 0;
                            continue;
                        }

                        if ($missingAttribute === 'country' ||
                            $missingAttribute === 'lastname' ||
                            $missingAttribute === 'firstname') {
                            if (!empty($address[$missingAttribute])) {
                                $User[$missingAttribute] = $address[$missingAttribute];
                                continue;
                            }

                            $User[$missingAttribute] = '';
                            continue;
                        }

                        $User[$missingAttribute] = '';
                    }
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

    /**
     * Set Order payment status (paid_status)
     *
     * @param int $status
     * @return void
     * @throws \QUI\Exception
     */
    abstract public function setPaymentStatus(int $status);

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

        $Payments     = Payments::getInstance();
        $calculations = $this->Articles->getCalculations();

        try {
            /* @todo die rundung muss währungsabhängig sein,
             * hierzu brauchen wir noch eine einstellung
             * quiqqer/package-currency#3
             */
            if (\round($calculations['sum'], 2) >= 0 && \round($calculations['sum'], 2) <= 0) {
                return $Payments->getPayment(
                    QUI\ERP\Accounting\Payments\Methods\Free\Payment::ID
                );
            }

            return $Payments->getPayment($this->paymentId);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
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
            $this->setAttribute('paid_status', self::PAYMENT_STATUS_OPEN);
            $this->calculatePayments();

            return;
        }

        QUI\ERP\Debug::getInstance()->log('Order:: add transaction start');

        $User     = QUI::getUserBySession();
        $paidData = $this->getAttribute('paid_data');
        $amount   = floatval($Transaction->getAmount());
        $date     = $Transaction->getDate();

        QUI::getEvents()->fireEvent(
            'quiqqerOrderAddTransactionBegin',
            [$this, $amount, $Transaction, $date]
        );


        if (!$amount) {
            return;
        }

        if (!\is_array($paidData)) {
            $paidData = \json_decode($paidData, true);
        }

        if (!\is_array($paidData)) {
            $paidData = [];
        }

        $isTxAlreadyAdded = function ($txid, $paidData) {
            foreach ($paidData as $paidEntry) {
                if (!isset($paidEntry['txid'])) {
                    continue;
                }

                if ($paidEntry['txid'] == $txid) {
                    return true;
                }
            }

            return false;
        };

        // already added
        if ($isTxAlreadyAdded($Transaction->getTxId(), $paidData)) {
            return;
        }

        $isValidTimeStamp = function ($timestamp) {
            return ((string)(int)$timestamp === $timestamp)
                   && ($timestamp <= PHP_INT_MAX)
                   && ($timestamp >= ~PHP_INT_MAX);
        };

        if ($isValidTimeStamp($date) === false) {
            $date = \strtotime($date);

            if ($isValidTimeStamp($date) === false) {
                $date = \time();
            }
        }

        $this->setAttribute('paid_date', $date);

        // calculations
        $this->Articles->calc();
        $listCalculations = $this->Articles->getCalculations();

        $this->setAttributes([
            'currency_data' => \json_encode($listCalculations['currencyData']),
            'nettosum'      => $listCalculations['nettoSum'],
            'subsum'        => $listCalculations['subSum'],
            'sum'           => $listCalculations['sum'],
            'vat_array'     => \json_encode($listCalculations['vatArray'])
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
     * @return Transaction[]
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

    //region shipping

    /**
     * @return QUI\ERP\Shipping\Api\ShippingInterface|null
     */
    public function getShipping()
    {
        if ($this->shippingId === null) {
            return null;
        }

        $Shipping = QUI\ERP\Shipping\Shipping::getInstance();

        try {
            return $Shipping->getShippingEntry($this->shippingId);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return null;
    }

    /**
     * @param QUI\ERP\Shipping\Api\ShippingInterface $Shipping
     */
    public function addShipping(QUI\ERP\Shipping\Api\ShippingInterface $Shipping)
    {
        $this->shippingId = $Shipping->getId();
    }

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
     * Add an article to the order
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
