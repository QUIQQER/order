<?php

/**
 * This file contains QUI\ERP\Order\Controls\Order\Order
 */

namespace QUI\ERP\Order\Controls\Order;

use QUI;

/**
 * Class Order
 * - Displays an order
 *
 * @package QUI\ERP\Order\Controls\Order
 */
class Order extends QUI\Controls\Control
{
    /**
     * @var null
     */
    protected $Order = null;

    /**
     * Order constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setAttributes([
            'Site'      => false,
            'data-qui'  => 'package/quiqqer/order/bin/frontend/controls/order/Order',
            'orderHash' => false
        ]);

        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__).'/Order.css');
        $this->addCSSClass('quiqqer-order-control-order');
    }

    /**
     * create the order html
     *
     * @return string
     *
     * @throws QUI\Exception
     */
    protected function onCreate()
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        try {
            $Order = $this->getOrder();
        } catch (QUI\ERP\Order\Exception $Exception) {
            // @todo error template

            QUI\System\Log::writeDebugException($Exception);

            return '';
        }

        // invoice
        $Invoice = null;

        try {
            $Invoice = $Order->getInvoice();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        // template
        $Engine->assign([
            'Order'        => $Order,
            'Articles'     => $Order->getArticles(),
            'Invoice'      => $Invoice,
            'Calculation'  => $Order->getPriceCalculation(),
            'PriceFactors' => $Order->getArticles()->getPriceFactors(),
        ]);

        return $Engine->fetch(dirname(__FILE__).'/Order.html');
    }

    /**
     * Returns the assigned order
     *
     * @return QUI\ERP\Order\OrderInProcess|Order|QUI\ERP\Order\Order
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     */
    public function getOrder()
    {
        if ($this->Order !== null) {
            return $this->Order;
        }

        $Handler     = QUI\ERP\Order\Handler::getInstance();
        $this->Order = $Handler->getOrderByHash($this->getAttribute('orderHash'));

        return $this->Order;
    }
}
