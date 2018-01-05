<?php

/**
 * This file contains QUI\ERP\Order\Controls\Finish
 */

namespace QUI\ERP\Order\Controls;

use QUI;
use QUI\ERP\Order\Handler;

/**
 * Class Finish
 *
 * @package QUI\ERP\Order\Controls
 */
class Finish extends AbstractOrderingStep
{
    /**
     * @return string
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Orders = Handler::getInstance();
        $Order  = $Orders->getOrderInProcess($this->getAttribute('orderId'));

        $Engine->assign(array(
            'User' => $Order->getCustomer()
        ));

        return $Engine->fetch(dirname(__FILE__).'/Finish.html');
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getName($Locale = null)
    {
        return 'Finish';
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return 'fa-check';
    }

    /**
     * @throws QUI\ERP\Order\Exception
     */
    public function validate()
    {
        $Order = $this->getOrder();

        if ($Order->isPosted() === false) {
            throw new QUI\ERP\Order\Exception(array(
                'quiqqer/order',
                'exception.order.is.not.finished'
            ));
        }
    }

    public function save()
    {
        // TODO: Implement save() method.
    }
}
