<?php

/**
 * This file contains QUI\ERP\Order\AbstractOrder
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\ERP\Accounting\ArticleList;

/**
 * Class AbstractOrder
 *
 * Main parent class for order classes
 * - Order
 * - OrderProcess
 *
 * @package QUI\ERP\Order
 */
abstract class AbstractOrder
{
    /**
     * Order is only created
     */
    const STATUS_CREATED = 0;

    /**
     * Order is posted (Invoice created)
     * Bestellung ist gebucht (Invoice erstellt)
     */
    const STATUS_POSTED = 1; // Bestellung ist gebucht (Invoice erstellt)

    /**
     * order id
     *
     * @var integer
     */
    protected $id;

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
     * @var mixed
     */
    protected $cDate;

    /**
     * @var ArticleList
     */
    protected $Articles = null;

    /**
     * @var null|QUI\ERP\User
     */
    protected $Customer = null;

    /**
     * Order constructor.
     *
     * @param array $data
     * @throws Exception
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

        $this->id        = (int)$data['id'];
        $this->invoiceId = $data['invoice_id'];
        $this->hash      = $data['hash'];
        $this->cDate     = $data['c_date'];

        $this->addressDelivery = json_decode($data['addressDelivery'], true);
        $this->addressInvoice  = json_decode($data['addressInvoice'], true);
        $this->data            = json_decode($data['data'], true);


        // user
        $this->customerId = (int)$data['customerId'];

        if (isset($data['customer'])) {
            $customer = json_decode($data['customer'], true);

            if (!isset($customer['id'])) {
                $customer['id'] = $this->customerId;
            }

            try {
                $this->setCustomer($customer);
            } catch (QUi\Exception $Exception) {
                QUI\System\Log::writeRecursive($customer);
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
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::addError($Exception->getMessage());
                }
            }
        }
    }


    //region API

    /**
     * Updates the order
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional, permission user, default = session user
     */
    abstract public function update($PermissionUser = null);

    //endregion

    //region getter

    /**
     * Return the order as an array
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'id'        => $this->id,
            'invoiceId' => $this->invoiceId,
            'hash'      => $this->hash,
            'cDate'     => $this->cDate,
            'data'      => $this->data,

            'customerId' => $this->customerId,
            'customer'   => $this->customer,

            'articles'        => $this->getArticles()->toArray(),
            'addressDelivery' => $this->getDeliveryAddress()->getAttributes(),
            'addressInvoice'  => $this->getInvoiceAddress()->getAttributes(),
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

        try {
            $User     = QUI::getUsers()->get($this->customerId);
            $Customer = QUI\ERP\User::convertUserToErpUser($User);

            $this->Customer = $Customer;

            return $this->Customer;
        } catch (QUI\Exception $Exception) {
        }

        return $Nobody;
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
     */
    public function setCustomer($User)
    {
        if (empty($User)) {
            return;
        }

        if (is_array($User)) {
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
}
