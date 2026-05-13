<?php

/**
 * This file contains QUI\ERP\Order\Controls\OrderProcess\Finish
 */

namespace QUI\ERP\Order\Controls\OrderProcess;

use QUI;

use function dirname;

/**
 * Class Finish
 *
 * @package QUI\ERP\Order\Controls
 */
class Finish extends QUI\ERP\Order\Controls\AbstractOrderingStep
{
    /**
     * Finish constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSClass('quiqqer-order-step-finish');
        $this->addCSSFile(dirname(__FILE__) . '/Finish.css');
    }

    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Order = $this->getOrder();
        $Handler = QUI\ERP\Order\Handler::getInstance();

        try {
            $Basket = $Handler->getBasketFromUser(QUI::getUserBySession());

            $Basket->clear();
            $Basket->setHash('');
            $Basket->save();
        } catch (QUI\Exception) {
            // no basket? all is fine
        }

        $Order->recalculate();

        $OrderControl = new QUI\ERP\Order\Controls\Order\Order([
            'Order' => $Order,
            'template' => 'OrderLikeBasket'
        ]);

        $Engine->assign([
            'User' => $Order->getCustomer(),
            'Order' => $Order,
            'orderHtml' => $OrderControl->create(),
            'orderHash' => $Order->getUUID()
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/Finish.html');
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getName(QUI\Locale | null $Locale = null): string
    {
        return 'Finish';
    }

    /**
     * @return string
     */
    public function getIcon(): string
    {
        return 'fa-check';
    }

    /**
     * @throws QUI\ERP\Order\Exception
     */
    public function validate(): void
    {
        $Order = $this->getOrder();

        if ($Order->isSuccessful()) {
            return;
        }

        if ($Order->isPosted() === false) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/order',
                'exception.order.is.not.finished'
            ]);
        }
    }

    /**
     * Placeholder
     *
     * @return void
     */
    public function save(): void
    {
    }
}
