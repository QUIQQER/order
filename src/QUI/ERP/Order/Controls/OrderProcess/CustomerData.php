<?php

/**
 * This file contains QUI\ERP\Order\Controls\OrderProcess\CustomerData
 */

namespace QUI\ERP\Order\Controls\OrderProcess;

use QUI;

/**
 * Class CustomerData
 *
 * @package QUI\ERP\Order\Controls\OrderProcess
 */
class CustomerData extends QUI\ERP\Order\Controls\AbstractOrderingStep
{
    /**
     * Basket constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = array())
    {
        parent::__construct($attributes);

        $this->setAttributes([
            'data-qui' => 'package/quiqqer/order/bin/frontend/controls/orderProcess/CustomerData'
        ]);

        $this->addCSSFile(dirname(__FILE__).'/CustomerData.css');
    }

    /**
     * @return string
     */
    public function getBody()
    {
        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            return '';
        }

        $Order    = $this->getOrder();
        $Customer = $Order->getCustomer();
        $Address  = $this->getInvoiceAddress();

        $Engine->assign([
            'User'      => $Customer,
            'Address'   => $Address,
            'countries' => QUI\Countries\Manager::getList()
        ]);

        return $Engine->fetch(dirname(__FILE__).'/CustomerData.html');
    }


    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getName($Locale = null)
    {
        return 'Customer';
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return 'fa-user-o';
    }

    /**
     * @throws QUI\ERP\Order\Exception
     */
    public function validate()
    {
        $Order   = $this->getOrder();
        $Address = $Order->getInvoiceAddress();

        $firstName = $Address->getAttribute('firstname');
        $lastName  = $Address->getAttribute('lastname');
        $street_no = $Address->getAttribute('street_no');
        $zip       = $Address->getAttribute('zip');
        $city      = $Address->getAttribute('city');
        $country   = $Address->getAttribute('country');

        /**
         * @param $field
         * @throws QUI\ERP\Order\Exception
         */
        $throwException = function ($field) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/order',
                'exception.missing.address.field',
                ['field' => $field]
            ]);
        };

        if (empty($firstName)) {
            $throwException(
                QUI::getLocale()->get('quiqqer/order', 'firstame')
            );
        }

        if (empty($lastName)) {
            $throwException(
                QUI::getLocale()->get('quiqqer/order', 'lastname')
            );
        }

        if (empty($street_no)) {
            $throwException(
                QUI::getLocale()->get('quiqqer/order', 'street_no')
            );
        }

        if (empty($zip)) {
            $throwException(
                QUI::getLocale()->get('quiqqer/order', 'zip')
            );
        }

        if (empty($city)) {
            $throwException(
                QUI::getLocale()->get('quiqqer/order', 'city')
            );
        }

        if (empty($country)) {
            $throwException(
                QUI::getLocale()->get('quiqqer/order', 'country')
            );
        }
    }

    /**
     * Order was ordered with costs
     *
     * @return void
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Permissions\Exception
     * @throws QUI\Exception
     */
    public function save()
    {
        if (isset($_REQUEST['current']) && $_REQUEST['current'] !== $this->getName()) {
            return;
        }

        if (!isset($_REQUEST['addressId'])) {
            return;
        }

        $Address = $this->getAddressById((int)$_REQUEST['addressId']);

        if ($Address === null) {
            return;
        }

        $fields = [
            'company',
            'salutation',
            'firstname',
            'lastname',
            'street_no',
            'zip',
            'city',
            'countries'
        ];

        foreach ($fields as $field) {
            if (isset($_REQUEST[$field])) {
                $Address->setAttribute($field, $_REQUEST[$field]);
            }
        }

        if (isset($_REQUEST['tel'])) {
            $Address->editPhone(0, $_REQUEST['tel']);
        }

        $Address->save();

        $this->getOrder()->setInvoiceAddress($Address);
        $this->getOrder()->save();
    }

    /**
     * @param $addressId
     * @return false|null|QUI\Users\Address
     */
    protected function getAddressById($addressId)
    {
        $User    = QUI::getUserBySession();
        $Address = null;

        try {
            $Address = $User->getAddress($addressId);
        } catch (QUI\Exception $Exception) {
            if ($addressId === 0) {
                try {
                    $Address = $User->getAddress($User->getAttribute('quiqqer.erp.address'));
                } catch (QUI\Exception $Exception) {
                    if (defined('QUIQQER_DEBUG')) {
                        QUI\System\Log::writeException($Exception);
                    }
                }
            } else {
                if (defined('QUIQQER_DEBUG')) {
                    QUI\System\Log::writeException($Exception);
                }
            }
        }

        if ($Address === null && $User instanceof QUI\Users\User) {
            try {
                $Address = $User->getStandardAddress();
            } catch (QUI\Users\Exception $Exception) {
                if (defined('QUIQQER_DEBUG')) {
                    QUI\System\Log::writeException($Exception);
                }
            }
        }

        return $Address;
    }

    /**
     * @return false|null|QUI\Users\Address
     */
    protected function getInvoiceAddress()
    {
        $Order    = $this->getOrder();
        $Customer = $Order->getCustomer();
        $User     = QUI::getUserBySession();

        try {
            $Address = $Order->getInvoiceAddress();

            if ($Address->getId()) {
                return $User->getAddress($Address->getId());
            }
        } catch (QUi\Exception $Exception) {
        }

        try {
            $Address = $Customer->getStandardAddress();

            if ($Address->getId()) {
                return $User->getAddress($Address->getId());
            }
        } catch (QUi\Exception $Exception) {
        }

        if ($User->getAttribute('quiqqer.erp.address')) {
            try {
                return $User->getAddress($User->getAttribute('quiqqer.erp.address'));
            } catch (QUI\Exception $Exception) {
            }
        }

        try {
            /* @var $User QUI\Users\User */
            return $User->getStandardAddress();
        } catch (QUI\Exception $Exception) {
        }

        return null;
    }
}
