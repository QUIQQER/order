<?php

/**
 * This file contains QUI\ERP\Order\Controls\OrderProcess\Finish
 */

namespace QUI\ERP\Order\Controls\OrderProcess;

use QUI;

/**
 * Class Finish
 *
 * @package QUI\ERP\Order\Controls
 */
class Finish extends QUI\ERP\Order\Controls\AbstractOrderingStep
{
    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Order  = $this->getAttribute('Order');

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

        if ($Order->isSuccessful()) {
            return;
        }

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
