<?php

/**
 * This file contains QUI\ERP\Order\FrontendUsers\Controls\UserOrders
 */

namespace QUI\ERP\Order\FrontendUsers\Controls;

use QUI;
use QUI\Control;
use QUI\FrontendUsers\Controls\Profile\ControlInterface;

/**
 * Class UserOrders
 *
 * @package QUI\ERP\Order\FrontendUSers\Controls
 */
class UserOrders extends Control implements ControlInterface
{
    /**
     * UserOrders constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->addCSSClass('quiqqer-order-profile-orders');
        $this->addCSSFile(\dirname(__FILE__).'/UserOrders.css');

        $this->setAttributes([
            'data-qui' => 'package/quiqqer/order/bin/frontend/controls/frontendusers/Orders',
            'page'     => 1,
            'limit'    => 5
        ]);

        parent::__construct($attributes);
    }

    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Orders = QUI\ERP\Order\Handler::getInstance();
        $User   = QUI::getUserBySession();

        if ($this->getAttribute('User')) {
            $User = $this->getAttribute('User');
        }

        if (!$this->getAttribute('limit')) {
            $this->setAttribute('limit', 5);
        }

        $limit        = (int)$this->getAttribute('limit');
        $sheetsMax    = 1;
        $sheetCurrent = (int)$this->getAttribute('page');
        $start        = ($sheetCurrent - 1) * $limit;

        $count = $Orders->countOrdersByUser($User);

        if ($count) {
            $sheetsMax = \ceil($count / $limit);
        }

        $orders = [];

        $result = $Orders->getOrdersByUser($User, [
            'order' => 'c_date DESC',
            'limit' => $start.','.$limit
        ]);

        foreach ($result as $Order) {
            /* @var $Order QUI\ERP\Order\Order */
            /* @var $View QUI\ERP\Order\OrderView */
            $View = $Order->getView();

            $View->setAttribute(
                'downloadLink',
                URL_OPT_DIR.'quiqqer/order/bin/frontend/order.pdf.php?order='.$View->getHash()
            );

            $orders[] = $View;
        }

        $this->setAttribute('data-qui-options-limit', $this->getAttribute('limit'));

        $Engine->assign([
            'orders'  => $orders,
            'this'    => $this,
            'Project' => $this->getProject(),
            'Site'    => $this->getSite(),

            'sheetsMax'    => $sheetsMax,
            'sheetCurrent' => $sheetCurrent,
            'sheetLimit'   => $limit,
            'sheetCount'   => $count
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/UserOrders.html');
    }

    /**
     * @param QUI\ERP\Order\Order|QUI\ERP\Order\OrderInProcess $Order
     * @return string
     * @throws QUI\Exception
     */
    public function renderOrder($Order)
    {
        if (!($Order instanceof QUI\ERP\Order\Order) &&
            !($Order instanceof QUI\ERP\Order\OrderView) &&
            !($Order instanceof QUI\ERP\Order\OrderInProcess)) {
            return '';
        }

        $Engine   = QUI::getTemplateManager()->getEngine();
        $Articles = $Order->getArticles();
        $Invoice  = null;

        $paidStatus = $Order->getAttribute('paid_status');
        $paidDate   = $Order->getAttribute('paid_date');

        $Articles->calc();

        if ($Order->hasInvoice()) {
            $Invoice    = $Order->getInvoice();
            $paidStatus = $Invoice->getAttribute('paid_status');
            $paidDate   = $Invoice->getAttribute('paid_date');
        }

        switch ((int)$paidStatus) {
            case QUI\ERP\Order\AbstractOrder::PAYMENT_STATUS_OPEN:
                $paymentStatus = QUI::getLocale()->get('quiqqer/order', 'payment.status.0');
                break;

            case QUI\ERP\Order\AbstractOrder::PAYMENT_STATUS_PAID:
                $Formatter = QUI::getLocale()->getDateFormatter();
                $date      = $Formatter->format($paidDate);

                $paymentStatus = QUI::getLocale()->get('quiqqer/order', 'payment.status.paid.text', [
                    'date' => $date
                ]);
                break;

            case QUI\ERP\Order\AbstractOrder::PAYMENT_STATUS_PLAN:
                $paymentStatus = QUI::getLocale()->get('quiqqer/order', 'payment.status.12');
                break;

            default:
            case QUI\ERP\Order\AbstractOrder::PAYMENT_STATUS_PART:
            case QUI\ERP\Order\AbstractOrder::PAYMENT_STATUS_ERROR:
            case QUI\ERP\Order\AbstractOrder::PAYMENT_STATUS_CANCELED:
            case QUI\ERP\Order\AbstractOrder::PAYMENT_STATUS_DEBIT:
                $paymentStatus = QUI::getLocale()->get('quiqqer/order', 'payment.status.0');
        }

        $PSHandler  = QUI\ERP\Order\ProcessingStatus\Handler::getInstance();
        $statusList = $PSHandler->getProcessingStatusList();

        $OrderStatus   = $Order->getProcessingStatus();
        $orderStatusId = 0;
        $orderStatus   = $PSHandler->getProcessingStatus(0)->getTitle();

        foreach ($statusList as $Status) {
            if ($OrderStatus && $Status->getId() === $OrderStatus->getId()) {
                $orderStatusId = $Status->getId();
                $orderStatus   = $Status->getTitle();
                break;
            }
        }

        $Engine->assign([
            'this'          => $this,
            'Project'       => $this->getProject(),
            'Order'         => $Order,
            'Payment'       => $Order->getPayment(),
            'Invoice'       => $Invoice,
            'Articles'      => $Articles,
            'articles'      => $Articles->getArticles(),
            'order'         => $Articles->toArray(),
            'paymentStatus' => $paymentStatus,
            'orderStatusId' => $orderStatusId,
            'orderStatus'   => $orderStatus,
            'Utils'         => new QUI\ERP\Order\Utils\Utils(),
            'orderUrl'      => QUI\ERP\Order\Utils\Utils::getOrderProcessUrlForHash(
                $this->getProject(),
                $Order->getHash()
            )
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/UserOrders.Order.html');
    }

    /**
     * @param QUI\ERP\Accounting\Article $Article
     * @return string
     *
     * @throws QUI\Exception
     */
    public function renderArticle(QUI\ERP\Accounting\Article $Article)
    {
        $Engine  = QUI::getTemplateManager()->getEngine();
        $Product = null;
        $Image   = null;

        try {
            $Product = QUI\ERP\Products\Handler\Products::getProductByProductNo(
                $Article->getArticleNo()
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        if (!empty($Product)) {
            try {
                $Image = $Product->getImage();
            } catch (QUI\Exception $Exception) {
            }
        }

        $Article->calc();

        $Engine->assign([
            'this'    => $this,
            'Article' => $Article,
            'Product' => $Product,
            'Image'   => $Image,
            'Project' => QUI::getProjectManager()->get()
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/UserOrders.Article.html');
    }

    /**
     * @return mixed|QUI\Projects\Site
     * @throws QUI\Exception
     */
    public function getSite()
    {
        if ($this->getAttribute('Site')) {
            return $this->getAttribute('Site');
        }

        $Site = QUI::getRewrite()->getSite();
        $this->setAttribute('Site', $Site);

        return $Site;
    }

    /**
     * event: on save
     */
    public function onSave()
    {
    }

    public function validate()
    {
    }
}
