<?php

/**
 * This file contains QUI\ERP\Order\Controls\Registration
 */

namespace QUI\ERP\Order\Controls\OrderProcess;

use QUI;

use function dirname;

/**
 * Class Basket
 * - Basket step
 *
 * @package QUI\ERP\Order\Basket
 */
class Registration extends QUI\ERP\Order\Controls\AbstractOrderingStep
{
    /**
     * Basket constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/Registration.css');

        $this->setAttributes([
            'data-qui' => 'package/quiqqer/order/bin/frontend/controls/orderProcess/Registration'
        ]);
    }

    /**
     * @param null $Locale
     * @return string
     */
    public function getName($Locale = null): string
    {
        return 'Registration';
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        return $Engine->fetch(dirname(__FILE__) . '/Registration.html');
    }

    /**
     * @return bool
     */
    public function hasOwnForm(): bool
    {
        return true;
    }

    /**
     *
     */
    public function validate(): void
    {
        // TODO: Implement validate() method.
    }

    /**
     * @return void
     */
    public function save(): void
    {
        // TODO: Implement save() method.
    }
}
