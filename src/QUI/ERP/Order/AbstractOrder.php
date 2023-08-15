<?php

/**
 * This file contains QUI\ERP\Order\AbstractOrder
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Accounting\Payments\Transactions\Handler as TransactionHandler;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Order\ProcessingStatus\Handler as ProcessingHandler;
use QUI\ERP\Shipping\ShippingStatus\Handler as ShippingStatusHandler;

use function array_filter;
use function array_flip;
use function date;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function method_exists;
use function preg_replace;
use function round;
use function strip_tags;
use function strtotime;
use function time;

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
    /* @deprecated */
    const PAYMENT_STATUS_OPEN = QUI\ERP\Constants::PAYMENT_STATUS_OPEN;

    /* @deprecated */
    const PAYMENT_STATUS_PAID = QUI\ERP\Constants::PAYMENT_STATUS_PAID;

    /* @deprecated */
    const PAYMENT_STATUS_PART = QUI\ERP\Constants::PAYMENT_STATUS_PART;

    /* @deprecated */
    const PAYMENT_STATUS_ERROR = QUI\ERP\Constants::PAYMENT_STATUS_ERROR;

    /* @deprecated */
    const PAYMENT_STATUS_CANCELED = QUI\ERP\Constants::PAYMENT_STATUS_CANCELED;

    /* @deprecated */
    const PAYMENT_STATUS_DEBIT = QUI\ERP\Constants::PAYMENT_STATUS_DEBIT;

    /* @deprecated */
    const PAYMENT_STATUS_PLAN = QUI\ERP\Constants::PAYMENT_STATUS_PLAN;

    /**
     * Order is only created
     *
     * @deprecated use
     */
    const STATUS_CREATED = QUI\ERP\Constants::ORDER_STATUS_CREATED;

    /**
     * Order is posted (Invoice created)
     * Bestellung ist gebucht (Invoice erstellt)
     *
     * @deprecated
     */
    const STATUS_POSTED = QUI\ERP\Constants::ORDER_STATUS_POSTED; // Bestellung ist gebucht (Invoice erstellt)

    /**
     * @deprecated
     */
    const STATUS_STORNO = QUI\ERP\Constants::ORDER_STATUS_STORNO; // Bestellung ist storniert

    /**
     * Article types
     */
    const ARTICLE_TYPE_PHYSICAL = 1;
    const ARTICLE_TYPE_DIGITAL = 2;
    const ARTICLE_TYPE_MIXED = 3;

    /**
     * order id
     *
     * @var integer
     */
    protected int $id;

    /**
     * @var string
     */
    protected ?string $idPrefix = null;

    /**
     * @var int
     */
    protected int $status = 0;

    /**
     * @var null
     */
    protected $Status = null;

    /**
     * @var null|QUI\ERP\Shipping\ShippingStatus\Status
     */
    protected ?QUI\ERP\Shipping\ShippingStatus\Status $ShippingStatus = null;

    /**
     * @var int
     */
    protected int $successful;

    /**
     * invoice ID
     *
     * @var int|bool
     */
    protected $invoiceId = false;

    /**
     * @var int|null
     */
    protected ?int $customerId;

    /**
     * @var array|null
     */
    protected ?array $customer = [];

    /**
     * @var array|null
     */
    protected ?array $addressInvoice = [];

    /**
     * @var array|null
     */
    protected ?array $addressDelivery = [];

    /**
     * @var array
     */
    protected array $articles = [];

    /**
     * @var array|null
     */
    protected ?array $data;
    /**
     * @var array
     */
    protected array $paymentData = [];

    /**
     * @var string
     */
    protected string $hash;

    /**
     * @var string
     */
    protected string $cDate;

    /**
     * Create user id
     *
     * @var integer
     */
    protected int $cUser;

    /**
     * @var ArticleList|null
     */
    protected ?ArticleList $Articles = null;

    /**
     * @var QUI\ERP\Comments|null
     */
    protected ?QUI\ERP\Comments $Comments = null;

    /**
     * @var QUI\ERP\Comments|null
     */
    protected ?QUI\ERP\Comments $History = null;

    /**
     * @var QUI\ERP\Comments|null
     */
    protected ?QUI\ERP\Comments $FrontendMessage = null;

    /**
     * @var QUI\ERP\Comments|null
     */
    protected ?QUI\ERP\Comments $StatusMails = null;

    /**
     * @var null|QUI\ERP\User
     */
    protected ?QUI\ERP\User $Customer = null;

    // payments

    /**
     * @var ?int
     */
    protected ?int $paymentId = null;

    /**
     * @var string|null
     */
    protected ?string $paymentMethod;

    /**
     * @var bool
     */
    protected bool $statusChanged = false;

    /**
     * @var QUI\ERP\Currency\Currency|null
     */
    protected ?QUI\ERP\Currency\Currency $Currency = null;

    //shipping

    /**
     * @var integer|null
     */
    protected ?int $shippingId = null;

    /**
     * Order constructor.
     *
     * @param array $data
     *
     * @throws Exception
     * @throws QUI\ERP\Exception
     */
    public function __construct(array $data = [])
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

        $this->id = (int)$data['id'];
        $this->hash = $data['hash'];
        $this->cDate = $data['c_date'];
        $this->cUser = (int)$data['c_user'];

        $this->setDataBaseData($data);

        try {
            QUI::getEvents()->fireEvent('quiqqerOrderInit', [$this]);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());
        }
    }

    /**
     * Set the db data to the order object
     *
     * @param array $data
     *
     * @throws QUI\ERP\Exception|\Exception
     */
    protected function setDataBaseData(array $data)
    {
        $this->invoiceId = $data['invoice_id'];

        $this->idPrefix = $data['id_prefix'];
        $this->addressDelivery = json_decode($data['addressDelivery'], true);
        $this->addressInvoice = json_decode($data['addressInvoice'], true);
        $this->data = json_decode($data['data'], true);

        if (isset($data['status'])) {
            $this->status = $data['status'];
        }

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

            if (!isset($customerData['isCompany']) && isset($this->customer['company'])) {
                $customerData['isCompany'] = !empty($this->customer['company']);
            }

            if (!isset($customerData['country']) && isset($customerData['address']['country'])) {
                $customerData['country'] = $customerData['address']['country'];
            }

            try {
                $this->setCustomer($customerData);
            } catch (QUi\Exception $Exception) {
                QUI\System\Log::writeRecursive($this->customer);
                QUI\System\Log::addWarning($Exception->getMessage());
            }

            if (isset($this->addressInvoice['id']) && $this->addressInvoice['id'] >= 0) {
                $this->Customer->setAddress($this->getInvoiceAddress());
            } elseif (isset($customerData['address']['id']) && $customerData['address']['id']) {
                $this->Customer->setAddress($this->getInvoiceAddress());
            } elseif (isset($customerData['quiqqer.erp.address'])) {
                try {
                    $User = QUI::getUsers()->get($this->Customer->getId());
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
            $articles = json_decode($data['articles'], true);

            if ($articles) {
                try {
                    $this->Articles = new ArticleList($articles);
                } catch (QUI\ERP\Exception $Exception) {
                    QUI\System\Log::addError($Exception->getMessage());
                }
            }
        }

        $Customer = $this->getCustomer();
        $Customer->setAddress($this->getDeliveryAddress());

        $this->Articles->setUser($Customer);
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

        // frontend messages
        $this->FrontendMessage = new QUI\ERP\Comments();

        if (isset($data['frontendMessages'])) {
            $this->FrontendMessage = QUI\ERP\Comments::unserialize($data['frontendMessages']);
        }

        // status mail
        $this->StatusMails = new QUI\ERP\Comments();

        if (isset($data['status_mails'])) {
            $this->StatusMails = QUI\ERP\Comments::unserialize($data['status_mails']);
        }

        // payment
        $this->paymentId = $data['payment_id'];
        $this->successful = (int)$data['successful'];

        if ($data['paid_data'] === null) {
            $data['paid_data'] = '';
        }

        $this->setAttributes([
            'paid_status' => (int)$data['paid_status'],
            'paid_data' => json_decode($data['paid_data'], true),
            'paid_date' => $data['paid_date'],
            'temporary_invoice_id' => $data['temporary_invoice_id'],
        ]);

        if (isset($data['payment_data'])) {
            $paymentData = QUI\Security\Encryption::decrypt($data['payment_data']);
            $paymentData = json_decode($paymentData, true);

            if (is_array($paymentData)) {
                $this->paymentData = $paymentData;
            }
        }

        // currency
        if (!empty($data['currency_data'])) {
            $currency = json_decode($data['currency_data'], true);

            if (is_string($currency)) {
                $currency = json_decode($currency, true);
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

        // shipping
        if (is_numeric($data['shipping_id'])) {
            $this->shippingId = (int)$data['shipping_id'];

            // validate shipping
            try {
                $this->validateShipping($this->getShipping());
            } catch (QUI\Exception $Exception) {
                $this->shippingId = null;
            }
        }

        try {
            $this->Status = ProcessingHandler::getInstance()->getProcessingStatus($data['status']);
            $this->status = (int)$data['status'];
        } catch (QUI\ERP\Order\ProcessingStatus\Exception $Exception) {
            // nothing
        }

        if (QUI::getPackageManager()->isInstalled('quiqqer/shipping') && isset($data['shipping_status'])) {
            try {
                $this->ShippingStatus = ShippingStatusHandler::getInstance()->getShippingStatus(
                    $data['shipping_status']
                );
            } catch (QUI\ERP\Shipping\ShippingStatus\Exception $Exception) {
                QUI\System\Log::addWarning($Exception->getMessage());
            }
        }
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

        $this->successful = 1;

        try {
            QUI::getEvents()->fireEvent('quiqqerOrderSuccessful', [$this]);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());
        }

        if ($this->isApproved()) {
            $this->triggerApprovalEvent();
        }

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/order',
                'order.history.set_successful'
            )
        );

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
        if ($this instanceof Order) {
            $this->Articles->recalculate();
            $this->save();

            return;
        }

        if ($this instanceof OrderInProcess && $this->getOrderId()) {
            // if an order exists for the order in process
            // never recalculate it
            return;
        }

        if ($this instanceof OrderInProcess && $this->isSuccessful()) {
            // if the order is successful
            // never recalculate it
            return;
        }

        $Customer = $this->getCustomer();

        if ($Basket === null) {
            $Basket = new QUI\ERP\Order\Basket\BasketOrder($this->getHash(), $Customer);
        }

        $ArticleList = new ArticleList();
        $ArticleList->setUser($Customer);

        $ProductCalc = QUI\ERP\Products\Utils\Calc::getInstance();
        $ProductCalc->setUser($Customer);

        $Products = $Basket->getProducts();
        $Products->setUser($Customer);
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
            [
                $Basket,
                $this,
                $Products
            ]
        );

        QUI::getEvents()->fireEvent(
            'quiqqerOrderBasketToOrderEnd',
            [
                $Basket,
                $this,
                $Products
            ]
        );

        $ArticleList->importPriceFactors(
            $Products->getPriceFactors()->toErpPriceFactorList()
        );

        $ArticleList->calc();

        $this->Articles = $ArticleList;
        $this->setCustomer($Customer);
        $this->update();
    }

    /**
     * Clears the complete order
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional, permission user, default = session user
     */
    abstract public function clear(QUI\Interfaces\Users\User $PermissionUser = null);

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
    abstract public function update(QUI\Interfaces\Users\User $PermissionUser = null);

    /**
     * Delete the order
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional, permission user, default = session user
     */
    abstract public function delete(QUI\Interfaces\Users\User $PermissionUser = null);

    /**
     * Is the order posted / submitted
     *
     * @return bool
     */
    abstract public function isPosted(): bool;

    //endregion

    //region getter

    /**
     * Return the order as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $status = '';
        $shippingStatus = false;
        $paymentId = '';
        $paidStatus = [];

        $articles = $this->getArticles()->toArray();
        $Payment = $this->getPayment();
        $ProcessingStatus = $this->getProcessingStatus();

        if ($Payment) {
            $paymentId = $Payment->getId();
        }

        if ($ProcessingStatus) {
            $status = $ProcessingStatus->getId();
        }

        try {
            $paidStatus = $this->getPaidStatusInformation();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        if ($this->getShippingStatus()) {
            $shippingStatus = $this->getShippingStatus()->getId();
        }

        $shipping = '';

        if ($this->getShipping()) {
            $shipping = $this->getShipping()->getId();
        }

        return [
            'id' => $this->id,
            'prefixedId' => $this->getPrefixedId(),
            'invoiceId' => $this->invoiceId,
            'hash' => $this->hash,
            'cDate' => $this->cDate,
            'cUser' => $this->cUser,
            'cUsername' => $this->getCreateUser()->getName(),
            'data' => $this->data,
            'customerId' => $this->customerId,
            'customer' => $this->getCustomer()->getAttributes(),
            'comments' => $this->getComments()->toArray(),
            'statusMails' => $this->getStatusMails()->toArray(),
            'currency' => $this->getCurrency()->toArray(),

            'articles' => $articles,
            'hasDeliveryAddress' => $this->hasDeliveryAddress(),
            'addressDelivery' => $this->getDeliveryAddress()->getAttributes(),
            'addressInvoice' => $this->getInvoiceAddress()->getAttributes(),
            'paymentId' => $paymentId,
            'status' => $status,
            'paidStatus' => $paidStatus,
            'shippingStatus' => $shippingStatus,
            'shipping' => $shipping,
            'shippingTracking' => $this->getDataEntry('shippingTracking'),
            'shippingConfirmation' => $this->getDataEntry('shippingConfirmation')
        ];
    }

    /**
     * Return the order id
     *
     * @return integer
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getIdPrefix(): ?string
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
    public function getPrefixedId(): string
    {
        return $this->getIdPrefix() . $this->getId();
    }

    /**
     * Return the order id
     * -> alias for getId(), this method is used in the calc classes
     *
     * @return integer
     */
    public function getCleanId(): int
    {
        return $this->getId();
    }

    /**
     * @return QUI\ERP\Accounting\Calculations
     *
     * @throws QUI\ERP\Exception
     * @throws QUI\Exception
     */
    public function getPriceCalculation(): QUI\ERP\Accounting\Calculations
    {
        $this->Articles->calc();

        return new QUI\ERP\Accounting\Calculations(
            $this->Articles->getCalculations(),
            $this->Articles->getArticles()
        );
    }

    /**
     * Is the order successful
     * -> Ist der Bestellablauf erfolgreich abgeschlossen
     *
     * @return int
     */
    public function isSuccessful(): int
    {
        return $this->successful;
    }

    /**
     * OrderProcess was successful and payment process was successful
     *
     * @return bool
     */
    public function isApproved(): bool
    {
        try {
            if (!$this->getPayment()) {
                return false;
            }

            $isApproved = $this->getPayment()->getPaymentType()->isApproved($this->getHash());
            $isSuccessful = $this->isSuccessful();

            return $isApproved && $isSuccessful;
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return false;
        }
    }

    /**
     * @return string
     */
    public function getInvoiceType(): string
    {
        try {
            return $this->getInvoice()->getType();
        } catch (QUI\Exception $Exception) {
            return '';
        }
    }

    /**
     * Return invoice address
     *
     * @return QUI\ERP\Address
     */
    public function getInvoiceAddress(): QUI\ERP\Address
    {
        return new QUI\ERP\Address($this->addressInvoice, $this->getCustomer());
    }

    /**
     * Return delivery address
     *
     * @return QUI\ERP\Address
     */
    public function getDeliveryAddress(): QUI\ERP\Address
    {
        $delivery = $this->addressDelivery;

        if (isset($delivery['id']) && $delivery['id'] === -1) { // quiqqer/order#156
            return $this->getInvoiceAddress();
        }

        // quiqqer/order#156
        // cleanup, to check the delivery address
        if (is_array($delivery)) {
            $delivery = array_filter($delivery);
        }

        if (isset($delivery['id'])) {
            unset($delivery['id']);
        }

        if (empty($delivery)) {
            return $this->getInvoiceAddress();
        }

        return new QUI\ERP\Address($this->addressDelivery, $this->getCustomer());
    }

    /**
     * Return the order articles list
     *
     * @return ArticleList
     */
    public function getArticles(): ArticleList
    {
        $this->Articles->setOrder($this);
        $this->Articles->setUser($this->getCustomer());
        $this->Articles->setCurrency($this->getCurrency());
        $this->Articles->calc();

        return $this->Articles;
    }

    /**
     * Return the order create date
     *
     * @return string (Y-m-d H:i:s)
     */
    public function getCreateDate(): string
    {
        return $this->cDate;
    }

    /**
     * Return the order create date
     *
     * @return QUI\Interfaces\Users\User
     */
    public function getCreateUser(): ?QUI\Interfaces\Users\User
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
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Return a data entry
     *
     * @param string $key
     * @return mixed|null
     */
    public function getDataEntry(string $key)
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
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Return the customer of the order
     *
     * @return QUI\ERP\User
     */
    public function getCustomer(): QUI\ERP\User
    {
        $Nobody = QUI\ERP\User::convertUserToErpUser(QUI::getUsers()->getNobody());

        if (!$this->customerId && !$this->Customer) {
            return $Nobody;
        }

        if ($this->customerId) {
            try {
                $User = QUI::getUsers()->get($this->customerId);
                $Customer = QUI\ERP\User::convertUserToErpUser($User);

                $this->Customer = $Customer;

                return $this->Customer;
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        if ($this->Customer) {
            $Address = $this->Customer->getStandardAddress();

            if (!$Address->getId()) {
                $this->Customer->setAddress(
                    new QUI\ERP\Address($this->addressInvoice, $this->Customer)
                );
            }

            return $this->Customer;
        }

        if ($this->customer) {
            try {
                $this->setCustomer($this->customer);

                $Address = $this->Customer->getStandardAddress();

                if (!$Address->getId()) {
                    $this->Customer->setAddress(
                        new QUI\ERP\Address($this->addressInvoice, $this->Customer)
                    );
                }

                return $this->Customer;
            } catch (QUi\Exception $Exception) {
                QUI\System\Log::writeRecursive($this->customer);
                QUI\System\Log::addWarning($Exception->getMessage());
            }
        }

        return $Nobody;
    }

    /**
     * Return the currency of the order
     * - At the moment it's the default currency of the system
     * - If we want to have different currencies, this can be implemented here
     *
     * @return QUI\ERP\Currency\Currency
     */
    public function getCurrency(): QUI\ERP\Currency\Currency
    {
        return $this->Currency;
    }

    /**
     * Has the order a delivery address?
     *
     * @return bool
     */
    public function hasDeliveryAddress(): bool
    {
        if (empty($this->addressDelivery)) {
            return false;
        }

        if (isset($this->addressDelivery['id']) && $this->addressDelivery['id'] === 0) {
            return false;
        }

        return true;
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
     * Clear up / remove the delivery address from the order
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
        if (
            $address instanceof QUI\ERP\Address ||
            $address instanceof QUI\Users\Address
        ) {
            $this->addressInvoice = $address->getAttributes();
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
    protected function parseAddressData(array $address): array
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
    public function setData(string $key, $value)
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

        if (is_array($User)) {
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

                        if (
                            $missingAttribute === 'country' ||
                            $missingAttribute === 'lastname' ||
                            $missingAttribute === 'firstname'
                        ) {
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

            $this->Customer = new QUI\ERP\User($User);
            $this->customerId = $this->Customer->getId();

            return;
        }

        if ($User instanceof QUI\ERP\User) {
            $this->Customer = $User;
            $this->customerId = $User->getId();

            return;
        }

        if ($User instanceof QUI\Interfaces\Users\User) {
            $this->Customer = QUI\ERP\User::convertUserToErpUser($User);
            $this->customerId = $this->Customer->getId();

            if (empty($this->addressInvoice)) {
                $this->setInvoiceAddress($this->Customer->getStandardAddress());
            }
        }
    }

    /**
     * @return void
     */
    public function removeCustomer()
    {
        $this->Customer = null;
        $this->customerId = 0;
    }

    /**
     * @param $date
     */
    public function setCreationDate($date)
    {
        $date = strtotime($date);
        $date = date('Y-m-d H:i:s', $date);

        $this->setAttribute('c_date', $date);
        $this->cDate = $date;
    }

    /**
     * Fire the onQuiqqerOrderApproved event.
     * This event is only fired once.
     *
     * @return void
     * @throws QUI\Exception
     * @throws QUI\ExceptionStack
     */
    protected function triggerApprovalEvent()
    {
        if ($this->getDataEntry('approvalSent')) {
            return;
        }

        QUI::getEvents()->fireEvent('onQuiqqerOrderApproved', [$this]);

        $this->setData('approvalSent', 1);
        $this->update();
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
    public function getPayment(): ?QUI\ERP\Accounting\Payments\Types\Payment
    {
        if ($this->paymentId === null) {
            return null;
        }

        $Currency = $this->getCurrency();
        $Payments = Payments::getInstance();
        $calculations = $this->Articles->getCalculations();

        try {
            if (
                round($calculations['sum'], $Currency->getPrecision()) >= 0
                && round($calculations['sum'], $Currency->getPrecision()) <= 0
            ) {
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
    public function getPaidStatusInformation(): array
    {
        QUI\ERP\Accounting\Calc::calculatePayments($this);

        return [
            'paidData' => $this->getAttribute('paid_data'),
            'paidDate' => $this->getAttribute('paid_date'),
            'paid' => $this->getAttribute('paid'),
            'toPay' => $this->getAttribute('toPay')
        ];
    }

    /**
     * Is the order already paid?
     *
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->getAttribute('paid_status') == QUI\ERP\Constants::PAYMENT_STATUS_PAID;
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
                    'order' => $this->getId(),
                    'payment' => $paymentId
                ]
            );
        }

        $this->paymentId = $Payment->getId();
        $this->paymentMethod = $Payment->getType();
    }

    /**
     * Clear the payment method of the order
     */
    public function clearPayment()
    {
        $this->paymentId = null;
        $this->paymentMethod = null;
    }

    /**
     * Set extra payment data to the order
     * This data is stored encrypted in the database
     *
     * @param string $key
     * @param string|integer|mixed $value
     */
    public function setPaymentData(string $key, $value)
    {
        $this->paymentData[$key] = $value;
    }

    /**
     * Return the complete payment data array (decrypted)
     *
     * @return array
     */
    public function getPaymentData(): array
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

        if (
            $this->getAttribute('paid_status') == QUI\ERP\Constants::PAYMENT_STATUS_PAID ||
            $this->getAttribute('paid_status') == QUI\ERP\Constants::PAYMENT_STATUS_CANCELED
        ) {
            $this->setAttribute('paid_status', QUI\ERP\Constants::PAYMENT_STATUS_OPEN);
            $this->calculatePayments();

            return;
        }

        QUI\ERP\Debug::getInstance()->log('Order:: add transaction start');

        $User = QUI::getUserBySession();
        $paidData = $this->getAttribute('paid_data');
        $amount = $Transaction->getAmount();
        $date = $Transaction->getDate();

        QUI::getEvents()->fireEvent(
            'quiqqerOrderAddTransactionBegin',
            [
                $this,
                $amount,
                $Transaction,
                $date
            ]
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
            'nettosum' => $listCalculations['nettoSum'],
            'subsum' => $listCalculations['subSum'],
            'sum' => $listCalculations['sum'],
            'vat_array' => json_encode($listCalculations['vatArray'])
        ]);

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/order',
                'history.message.addTransaction',
                [
                    'username' => $User->getName(),
                    'uid' => $User->getId(),
                    'txid' => $Transaction->getTxId()
                ]
            )
        );

        QUI::getEvents()->fireEvent(
            'addTransaction',
            [
                $this,
                $amount,
                $Transaction,
                $date
            ]
        );

        $this->calculatePayments();

        QUI::getEvents()->fireEvent(
            'quiqqerOrderAddTransactionEnd',
            [
                $this,
                $amount,
                $Transaction,
                $date
            ]
        );
    }

    /**
     * Alias for getTransactions
     */
    public function getPayments(): array
    {
        return $this->getTransactions();
    }

    /**
     * Return all transactions related to the order
     *
     * @return Transaction[]
     */
    public function getTransactions(): array
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
     * Return the shipping from the order
     *
     * @return QUI\ERP\Shipping\Types\ShippingEntry|null
     */
    public function getShipping(): ?QUI\ERP\Shipping\Types\ShippingEntry
    {
        if ($this->shippingId === null) {
            return null;
        }

        if (!QUI::getPackageManager()->isInstalled('quiqqer/shipping')) {
            return null;
        }


        $Shipping = QUI\ERP\Shipping\Shipping::getInstance();

        try {
            $ShippingEntry = $Shipping->getShippingEntry($this->shippingId);
            $ShippingEntry->setOrder($this);

            return $ShippingEntry;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        try {
            QUI::getEvents()->fireEvent('quiqqerOrderShippingOnEmpty', [$this]);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addInfo($Exception->getMessage());
            QUI\System\Log::addInfo($Exception->getTraceAsString());
        }

        return null;
    }

    /**
     * Set a shipping to the order
     *
     * @param QUI\ERP\Shipping\Api\ShippingInterface $Shipping
     * @throws \QUI\Exception
     */
    public function setShipping(QUI\ERP\Shipping\Api\ShippingInterface $Shipping)
    {
        $this->validateShipping($Shipping);
        $this->shippingId = $Shipping->getId();
    }

    /**
     * Remove the shipping from the order
     */
    public function removeShipping()
    {
        $this->shippingId = null;
    }

    /**
     * Shipping validation is only for the frontend
     *
     * @param null|QUI\ERP\Shipping\Api\ShippingInterface $Shipping
     * @throws \QUI\Exception
     */
    public function validateShipping(?QUI\ERP\Shipping\Api\ShippingInterface $Shipping)
    {
        // no validation for the backend, shipping validation is only for the frontend
        if (QUI::isBackend()) {
            return;
        }

        if ($Shipping === null) {
            return;
        }

        if (!$this->Articles->count()) {
            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.shipping.is.not.valid.no.articles')
            );
        }

        if (
            !method_exists($Shipping, 'setOrder')
            || !method_exists($Shipping, 'isValid')
            || !method_exists($Shipping, 'canUsedInOrder')
            || !method_exists($Shipping, 'canUsedBy')
        ) {
            return;
        }

        // validate shipping
        $Shipping->setOrder($this);

        if (
            !$Shipping->isValid()
            || !$Shipping->canUsedInOrder($this)
            || !$Shipping->canUsedBy($this->getCustomer(), $this)
        ) {
            $this->shippingId = false;

            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.shipping.is.not.valid')
            );
        }
    }

    /**
     * @return void
     * @throws \QUI\Exception
     */
    public function sendShippingConfirmation()
    {
        Mail::sendOrderShippingConfirmation($this);
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
    public function removeData(string $key)
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
    public function count(): int
    {
        return $this->Articles->count();
    }

    /**
     * Get the type of articles that are in the order.
     *
     * @return int - see self::ARTICLE_TYPE_*
     */
    public function getArticleType(): int
    {
        $digital = false;
        $physical = false;

        foreach ($this->Articles->getArticles() as $Article) {
            $articleId = $Article->getId();

            if (empty($articleId) || !is_numeric($articleId)) {
                continue;
            }

            try {
                $Product = QUI\ERP\Products\Handler\Products::getProduct((int)$articleId);

                if ($Product instanceof QUI\ERP\Products\Product\Types\DigitalProduct) {
                    $digital = true;
                } else {
                    $physical = true;
                }

                if ($physical && $digital) {
                    return self::ARTICLE_TYPE_MIXED;
                }
            } catch (\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        if ($digital) {
            return self::ARTICLE_TYPE_DIGITAL;
        }

        return self::ARTICLE_TYPE_PHYSICAL;
    }

    //endregion


    //region comments

    /**
     * Add a comment
     *
     * @param string $message
     */
    public function addComment(string $message)
    {
        $message = strip_tags(
            $message,
            '<div><span><pre><p><br><hr>
            <ul><ol><li><dl><dt><dd><strong><em><b><i><u>
            <img><table><tbody><td><tfoot><th><thead><tr>'
        );

        $this->Comments->addComment($message);
    }

    /**
     * Return the comments
     *
     * @return null|QUI\ERP\Comments
     */
    public function getComments(): ?QUI\ERP\Comments
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
    public function addHistory(string $message)
    {
        $this->History->addComment($message);
    }

    /**
     * Return the history object
     *
     * @return null|QUI\ERP\Comments
     */
    public function getHistory(): ?QUI\ERP\Comments
    {
        return $this->History;
    }

    //endregion

    //region frontend messages

    /**
     * Add a frontend messages
     *
     * @param string $message
     */
    public function addFrontendMessage(string $message)
    {
        $this->FrontendMessage->addComment($message);
        $this->saveFrontendMessages();
    }

    /**
     * Return the frontend message object
     *
     * @return null|QUI\ERP\Comments
     */
    public function getFrontendMessages(): ?QUI\ERP\Comments
    {
        return $this->FrontendMessage;
    }

    /**
     * Clears the messages and save this status to the database
     */
    public function clearFrontendMessages()
    {
        $this->FrontendMessage->clear();
        $this->saveFrontendMessages();
    }

    /**
     * Saves the frontend messages to the order
     *
     * @return mixed
     */
    abstract protected function saveFrontendMessages();

    //endregion

    //region status mails

    /**
     * Add a status mail
     *
     * @param string $message
     */
    public function addStatusMail(string $message)
    {
        $message = preg_replace('#<br\s*/?>#i', "\n", $message);
        $message = strip_tags($message);

        $this->StatusMails->addComment($message);
    }

    /**
     * Return the status mails
     *
     * @return null|QUI\ERP\Comments
     */
    public function getStatusMails(): ?QUI\ERP\Comments
    {
        return $this->StatusMails;
    }

    //endregion

    //region process status

    /**
     * Return the order status (processing status)
     * This status is the custom status of the order system
     * Ths status can vary
     *
     * @return QUI\ERP\Order\ProcessingStatus\Status
     */
    public function getProcessingStatus(): ?ProcessingStatus\Status
    {
        if ($this->Status !== null) {
            return $this->Status;
        }

        $Handler = ProcessingHandler::getInstance();

        try {
            $this->Status = $Handler->getProcessingStatus($this->status);
        } catch (QUI\Exception $Exception) {
        }

        // use default status
        if ($this->Status === null) {
            try {
                $this->Status = $Handler->getProcessingStatus(
                    Settings::getInstance()->get('orderStatus', 'standard')
                );
            } catch (QUI\Exception $Exception) {
                // nothing
            }
        }

        if ($this->Status === null) {
            try {
                $this->Status = $Handler->getProcessingStatus(0);
            } catch (QUI\Exception $Exception) {
                // nothing
            }
        }

        return $this->Status;
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
                $Handler = ProcessingHandler::getInstance();
                $Status = $Handler->getProcessingStatus($status);
            } catch (QUI\ERP\Order\ProcessingStatus\Exception $Exception) {
                QUI\System\Log::addWarning($Exception->getMessage());

                return;
            }
        }

        $OldStatus = $this->getProcessingStatus();

        if ($OldStatus->getId() !== $Status->getId()) {
            $this->status = $Status->getId();
            $this->Status = $Status;

            $this->statusChanged = true;
        } else {
            $this->statusChanged = false;
        }
    }

    //endregion

    //region shipping status

    /**
     * Return the shipping status
     * -> This method only works if shipping is installed
     *
     * @return QUI\ERP\Shipping\ShippingStatus\Status|bool
     */
    public function getShippingStatus()
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/shipping')) {
            return false;
        }

        if ($this->ShippingStatus !== null) {
            return $this->ShippingStatus;
        }

        try {
            $this->ShippingStatus = ShippingStatusHandler::getInstance()
                ->getShippingStatus($this->getAttribute('shipping_status'));
        } catch (QUI\Exception $Exception) {
        }

        // use default status
        if ($this->ShippingStatus === null) {
            return false;
        }

        return $this->ShippingStatus;
    }

    /**
     * Set a shipping status to the order
     * -> This method only works if shipping is installed
     * -> when the status changes, it triggers the onShippingStatusChange event
     *
     * @param int|QUI\ERP\Shipping\ShippingStatus\Status $status
     */
    public function setShippingStatus($status)
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/shipping')) {
            return;
        }

        if ($status instanceof QUI\ERP\Shipping\ShippingStatus\Status) {
            $Status = $status;
        } else {
            try {
                $Status = ShippingStatusHandler::getInstance()->getShippingStatus($status);
            } catch (QUI\ERP\Shipping\ShippingStatus\Exception $Exception) {
                QUI\System\Log::addWarning($Exception->getMessage());

                return;
            }
        }

        $OldStatus = $this->ShippingStatus;
        $this->ShippingStatus = $Status;

        if ($OldStatus !== $this->ShippingStatus) {
            $this->History->addComment(
                QUI::getLocale()->get(
                    'quiqqer/order',
                    'message.change.order.shipping.status',
                    [
                        'status' => $Status->getTitle(),
                        'statusId' => $Status->getId()
                    ]
                )
            );

            $this->update();

            try {
                QUI::getEvents()->fireEvent('quiqqerOrderShippingStatusChange', [$this]);
            } catch (\Exception $Exception) {
                QUI\System\Log::addError($Exception->getMessage());
            }
        }
    }

    //endregion
}
