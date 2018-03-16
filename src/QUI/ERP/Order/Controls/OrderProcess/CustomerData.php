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
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->setAttributes([
            'data-qui'      => 'package/quiqqer/order/bin/frontend/controls/orderProcess/CustomerData',
            'data-validate' => 0
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

        $User    = null;
        $Address = $this->getInvoiceAddress();

        if ($Address) {
            $User = $Address->getUser();
        }

        if (!$User) {
            try {
                $Customer = $this->getOrder()->getCustomer();
                $User     = QUI::getUsers()->get($Customer->getId());
            } catch (QUI\Exception $Exception) {
                $User = QUI::getUserBySession();
            }
        }

        if (!$Address) {
            try {
                /* @var $User \QUI\Users\User */
                $Address = $User->getStandardAddress();
            } catch (QUI\Users\Exception $Exception) {
                // user has no address
                // create a new standard address
                $Address = $User->addAddress();
            }
        }

        $isB2B = function () use ($User) {
            if (!$User) {
                return '';
            }

            if ($User->getAttribute('quiqqer.erp.isNettoUser') === QUI\ERP\Utils\User::IS_NETTO_USER) {
                return ' selected="selected"';
            }

            if ($User->getAttribute('quiqqer.erp.isNettoUser') !== false) {
                return '';
            }

            if (QUI\ERP\Utils\Shop::isB2B()) {
                return ' selected="selected"';
            }

            return '';
        };


        $commentCustomer = QUI::getSession()->get('comment-customer');
        $commentMessage  = QUI::getSession()->get('comment-message');

        if (!empty($commentCustomer)) {
            $commentCustomer = QUI\Utils\Security\Orthos::clear($commentCustomer);
        }

        if (!empty($commentMessage)) {
            $commentMessage = QUI\Utils\Security\Orthos::clear($commentMessage);
        }

        try {
            $this->validate();
            $this->setAttribute('data-validate', 1);
        } catch (QUI\ERP\Order\Exception $Exception) {
            $this->setAttribute('data-validate', 0);
        }

        $Engine->assign([
            'User'            => $User,
            'Address'         => $Address,
            'isB2B'           => QUI\ERP\Utils\Shop::isB2B(),
            'b2bSelected'     => $isB2B(),
            'commentMessage'  => $commentMessage,
            'commentCustomer' => $commentCustomer
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
        $this->validateAddress(
            $this->getOrder()->getInvoiceAddress()
        );
    }

    /**
     * Checks if the address is valid for the order customer data
     *
     * @param QUI\Users\Address $Address
     * @throws QUI\ERP\Order\Exception
     */
    public static function validateAddress(QUI\Users\Address $Address)
    {
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
                QUI::getLocale()->get('quiqqer/order', 'firstname')
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

        // @todo validate company
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
            'country'
        ];

        foreach ($fields as $field) {
            if (isset($_REQUEST[$field])) {
                $Address->setAttribute($field, $_REQUEST[$field]);
            }
        }

        if (isset($_REQUEST['tel'])) {
            $Address->editPhone(0, $_REQUEST['tel']);
        }

        // comment
        if (!empty($_REQUEST['comment-customer'])) {
            QUI::getSession()->set('comment-customer', $_REQUEST['comment-customer']);
        }

        if (!empty($_REQUEST['comment-message'])) {
            QUI::getSession()->set('comment-message', $_REQUEST['comment-message']);
        }

        // user data
        $User = $Address->getUser();

        if (isset($_REQUEST['businessType'])) {
            if ($_REQUEST['businessType'] === 'b2b') {
                $User->setAttribute('quiqqer.erp.isNettoUser', QUI\ERP\Utils\User::IS_NETTO_USER);
            } else {
                $User->setAttribute('quiqqer.erp.isNettoUser', QUI\ERP\Utils\User::IS_BRUTTO_USER);
            }
        }

        // @todo validate vat id??
        $currentVat = $User->getAttribute('quiqqer.erp.euVatId');

        if (isset($_REQUEST['vatId']) && empty($currentVat)) {
            $User->setAttribute('quiqqer.erp.euVatId', $_REQUEST['vatId']);
        }

        $User->save();
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
                    QUI\System\Log::writeDebugException($Exception);
                }
            } else {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        if ($Address === null && $User instanceof QUI\Users\User) {
            try {
                $Address = $User->getStandardAddress();
            } catch (QUI\Users\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
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

    /**
     * event on execute payable status
     */
    public function onExecutePayableStatus()
    {
        $message = '';

        if (QUI::getSession()->get('comment-customer')) {
            $message .= QUI::getSession()->get('comment-customer')."\n";
        }

        if (QUI::getSession()->get('comment-message')) {
            $message .= QUI::getSession()->get('comment-message');
        }

        $message = trim($message);

        if (empty($message)) {
            return;
        }

        $Comments = $this->getOrder()->getComments();
        $comments = $Comments->toArray();

        // look if the same comment already exists
        foreach ($comments as $comment) {
            if ($comment['message'] === $message) {
                return;
            }
        }

        $Comments->addComment($message);

        try {
            $this->getOrder()->save(QUI::getUsers()->getSystemUser());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }
}
