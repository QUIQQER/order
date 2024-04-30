<?php

/**
 * This file contains QUI\ERP\Order\Controls\OrderProcess\Delivery
 */

namespace QUI\ERP\Order\Controls\OrderProcess;

use QUI;

use QUI\Locale;

use function dirname;

/**
 * Class Delivery
 *
 * @package QUI\ERP\Order\Controls
 * @todo in delivery
 */
class Delivery extends QUI\ERP\Order\Controls\AbstractOrderingStep
{
    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Order = $this->getAttribute('Order');

        $Engine->assign([
            'User' => $Order->getCustomer()
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/Delivery.html');
    }

    /**
     * @param null|Locale $Locale
     * @return string
     */
    public function getName(Locale $Locale = null): string
    {
        return 'Delivery';
    }

    /**
     * @return string
     */
    public function getIcon(): string
    {
        return 'fa-truck';
    }

    public function validate(): void
    {
        // TODO: Implement validate() method.
    }


    public function save(): void
    {
        // TODO: Implement save() method.
    }
}
