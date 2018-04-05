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
class Order extends QUI\Control
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
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        try {
            $Order = $this->getOrder();
        } catch (QUI\ERP\Order\Exception $Exception) {
            // @todo error template

            QUI\System\Log::writeDebugException($Exception);

            return '';
        }

        $Invoice = null;

        if ($Order instanceof QUI\ERP\Order\Order) {
            $View = $Order->getView();
        } else {
            // Order in process
            $View = $Order;
        }

        $View->setAttribute(
            'downloadLink',
            URL_OPT_DIR.'quiqqer/order/bin/frontend/order.pdf.php?order='.$View->getHash()
        );

        // invoice
        try {
            if ($Order->hasInvoice()) {
                $Invoice = $Order->getInvoice();
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        // template
        $Engine->assign([
            'Order'        => $View,
            'Articles'     => $View->getArticles(),
            'Invoice'      => $Invoice,
            'Calculation'  => $View->getPriceCalculation(),
            'Vats'         => $View->getPriceCalculation()->getVat(),
            'PriceFactors' => $View->getArticles()->getPriceFactors(),
            'Payment'      => $View->getPayment()
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
