<?php

/**
 * use QUI\ERP\Order\Basket\Handler
 */

namespace QUI\ERP\Order\Basket;

use QUI;

/**
 * Class Handler
 *
 * @package QUI\ERP\Order\Basket
 */
class Handler extends QUI\Utils\Singleton
{
    /**
     * Return the order table
     *
     * @return string
     */
    public function table()
    {
        return QUI::getDBTableName('baskets');
    }

    /**
     * Return a specific Basket
     *
     * @param string|integer $basketId
     * @param QUI\Interfaces\Users\User|null $User
     * @return Basket
     */
    public function get($basketId, $User = null)
    {
        return new Basket($basketId, $User);
    }

    /**
     * Return the data of a wanted order
     *
     * @param string|integer $orderId
     * @param string|integer $uid
     * @return array
     *
     * @throws QUI\Erp\Order\Exception
     */
    public function getBasketData($orderId, $uid)
    {
        $result = QUI::getDataBase()->fetch(array(
            'from'  => $this->table(),
            'where' => array(
                'id'  => $orderId,
                'uid' => $uid
            ),
            'limit' => 1
        ));

        if (!isset($result[0])) {
            throw new QUI\Erp\Order\Exception(
                'Basket not found',
                404
            );
        }

        return $result[0];
    }

    /**
     *
     */
    public function search()
    {

    }
}