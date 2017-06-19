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
    protected $uid;

    /**
     * @var array
     */
    protected $user = array();

    /**
     * @var array
     */
    protected $address = array();

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
     * Order constructor.
     *
     * @param array $data
     * @throws Exception
     */
    public function __construct($data = array())
    {
        $needles = Factory::getInstance()->getOrderConstructNeedles();

        foreach ($needles as $needle) {
            if (!isset($data[$needle])) {
                throw new Exception(array(
                    'quiqqer/order',
                    'exception.order.construct.needle.missing',
                    array('needle', $needle)
                ));
            }
        }

        $this->id        = $data['id'];
        $this->invoiceId = $data['invoice_id'];
        $this->uid       = $data['uid'];
        $this->hash      = $data['hash'];
        $this->cDate     = $data['c_date'];

        $this->user    = json_decode($data['user'], true);
        $this->address = json_decode($data['address'], true);
        $this->data    = json_decode($data['data'], true);

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
     * Return address array
     *
     * @return array
     */
    public function getAddress()
    {
        return $this->address;
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
     * Return the order articles list
     *
     * @return ArticleList
     */
    public function getArticles()
    {
        return $this->Articles;
    }
}