<?php

/**
 * This file contains QUI\ERP\Order\Controls\OrderProcess\CustomerData
 */

namespace QUI\ERP\Order\Controls\OrderProcess;

use QUI;
use QUI\Users\User;

use function dirname;
use function json_decode;
use function trim;

/**
 * Class CustomerData
 *
 * @package QUI\ERP\Order\Controls\OrderProcess
 *
 * @event quiqqerOrderCustomerDataSave [ self ]
 * @event quiqqerOrderCustomerDataSaveEnd [ self ]
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

        $this->addCSSClass('quiqqer-order-customerData-container');
        $this->addCSSFile(dirname(__FILE__) . '/CustomerData.css');
    }

    /**
     * Return the body
     *
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
                /* @var $User User */
                $Address = $User->getStandardAddress();
            } catch (QUI\Users\Exception $Exception) {
                // user has no address
                // create a new standard address
                $Address = $User->addAddress();
            }
        }

        try {
            $this->getOrder()->setInvoiceAddress($Address);
            $this->getOrder()->save();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }


        $isUserB2B = function () use ($User) {
            if (!$User) {
                return '';
            }

            if ($User->getAttribute('quiqqer.erp.isNettoUser') === QUI\ERP\Utils\User::IS_NETTO_USER) {
                return ' selected="selected"';
            }

            if ($User->getAttribute('quiqqer.erp.isNettoUser') !== false) {
                return '';
            }

            if (QUI\ERP\Utils\Shop::isB2CPrioritized() ||
                QUI\ERP\Utils\Shop::isOnlyB2C()) {
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

        // frontend users address profile settings
        try {
            $Conf     = QUI::getPackage('quiqqer/frontend-users')->getConfig();
            $settings = $Conf->getValue('profile', 'addressFields');

            if (!empty($settings)) {
                $settings = json_decode($settings, true);
            }
        } catch (QUI\Exception $Exception) {
            $settings = [];
        }

        if (empty($settings) || is_string($settings)) {
            $settings = [];
        }

        $settings                 = QUI\FrontendUsers\Controls\Address\Address::checkSettingsArray($settings);
        $businessTypeIsChangeable = !(QUI\ERP\Utils\Shop::isOnlyB2C() || QUI\ERP\Utils\Shop::isOnlyB2B());

        $isB2B     = QUI\ERP\Utils\Shop::isB2B();
        $isB2C     = QUI\ERP\Utils\Shop::isB2C();
        $isOnlyB2B = QUI\ERP\Utils\Shop::isOnlyB2B();
        $isOnlyB2C = QUI\ERP\Utils\Shop::isOnlyB2C();

        $Engine->assign([
            'User'            => $User,
            'Address'         => $Address,
            'Order'           => $this->getOrder(),
            'b2bSelected'     => $isUserB2B(),
            'commentMessage'  => $commentMessage,
            'commentCustomer' => $commentCustomer,
            'settings'        => $settings,

            'businessTypeIsChangeable' => $businessTypeIsChangeable,
            'isB2C'                    => $isB2C,
            'isB2B'                    => $isB2B,
            'isOnlyB2B'                => $isOnlyB2B,
            'isOnlyB2C'                => $isOnlyB2C,
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/CustomerData.html');
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
        $Address = $this->getInvoiceAddress();

        try {
            if ($Address &&
                $Address->getId() !== $this->getOrder()->getInvoiceAddress()->getId()) {
                $this->getOrder()->setInvoiceAddress($Address);
                $this->getOrder()->save();
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

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
//        $zip       = $Address->getAttribute('zip');
        $city    = $Address->getAttribute('city');
        $country = $Address->getAttribute('country');

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

//        if (empty($zip)) {
//            $throwException(
//                QUI::getLocale()->get('quiqqer/order', 'zip')
//            );
//        }

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
     * @param QUI\Users\Address $Address
     * @return bool
     */
    protected function isAddressValid(QUI\Users\Address $Address)
    {
        try {
            $this->validateAddress($Address);
        } catch (QUI\Exception $Exception) {
            return false;
        }

        return true;
    }

    /**
     * @param QUI\Users\Address $Address
     * @return bool
     */
    protected function isAddressEmpty(QUI\Users\Address $Address)
    {
        $firstName = $Address->getAttribute('firstname');
        $lastName  = $Address->getAttribute('lastname');
        $street_no = $Address->getAttribute('street_no');
        $zip       = $Address->getAttribute('zip');
        $city      = $Address->getAttribute('city');
        $country   = $Address->getAttribute('country');

        if (empty($firstName)
            && empty($lastName)
            && empty($street_no)
            && empty($zip)
            && empty($city)
            && empty($country)
        ) {
            return true;
        }

        return false;
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

        QUI::getEvents()->fireEvent('quiqqerOrderCustomerDataSave', [$this]);

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

        if (isset($_REQUEST['street']) || isset($_REQUEST['street_number'])) {
            $street = '';

            if (isset($_REQUEST['street'])) {
                $street = trim($_REQUEST['street']);
            }

            if (isset($_REQUEST['street_number'])) {
                $street = $street . ' ' . trim($_REQUEST['street_number']);
            }

            $street = trim($street);

            $_REQUEST['street_no'] = $street;
        }

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
                $User->setCompanyStatus(true);
            } else {
                $User->setAttribute('quiqqer.erp.isNettoUser', QUI\ERP\Utils\User::IS_BRUTTO_USER);
                $User->setCompanyStatus(false);
            }
        } else {
            $User->setCompanyStatus(!empty($Address->getAttribute('company')));
        }

        $addressId = $User->getAttribute('quiqqer.erp.address');

        if (empty($addressId)) {
            $User->setAttribute('quiqqer.erp.address', $Address->getId());
        }


        // @todo validate vat id??
        $currentVat = $User->getAttribute('quiqqer.erp.euVatId');

        if (isset($_REQUEST['vatId']) && empty($currentVat)) {
            $User->setAttribute('quiqqer.erp.euVatId', $_REQUEST['vatId']);
        }

        // firstname lastname
        // because user makes an update to the address object
        if (!empty($_REQUEST['firstname']) && $User->getAttribute('firstname') === '') {
            $User->setAttribute('firstname', $_REQUEST['firstname']);
        }

        if (!empty($_REQUEST['lastname']) && $User->getAttribute('lastname') === '') {
            $User->setAttribute('lastname', $_REQUEST['lastname']);
        }


        $User->save();
        $Address->save();

        $User->refresh();

        $this->getOrder()->setInvoiceAddress($Address);

        if (isset($_REQUEST['shipping-address']) && $_REQUEST['shipping-address'] == -1) {
            $this->getOrder()->setDeliveryAddress([
                'id' => -1
            ]);
        }

        $this->getOrder()->setCustomer($User);
        $this->getOrder()->save();

        QUI::getEvents()->fireEvent('quiqqerOrderCustomerDataSaveEnd', [$this]);
    }

    /**
     * Return the address by its id
     *
     * @param integer $addressId
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

        if ($Address === null && $User instanceof User) {
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

            if ($Address->getId() && !$this->isAddressEmpty($Address)) {
                return $User->getAddress($Address->getId());
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        try {
            $Address = $Customer->getStandardAddress();

            if ($Address->getId() && !$this->isAddressEmpty($Address)) {
                return $User->getAddress($Address->getId());
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        if ($User->getAttribute('quiqqer.erp.address')) {
            try {
                $Address = $User->getAddress($User->getAttribute('quiqqer.erp.address'));

                return $Address;
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        try {
            /* @var $User User */
            return $User->getStandardAddress();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return null;
    }

    /**
     * event on order is successful
     *
     * @param QUI\ERP\Order\AbstractOrder $Order
     */
    public static function parseSessionOrderCommentsToOrder(QUI\ERP\Order\AbstractOrder $Order)
    {
        $message = '';

        if (QUI::getSession()->get('comment-customer')) {
            $message .= QUI::getSession()->get('comment-customer') . "\n";
        }

        if (QUI::getSession()->get('comment-message')) {
            $message .= QUI::getSession()->get('comment-message');
        }

        $message = trim($message);

        if (empty($message)) {
            return;
        }

        $Comments = $Order->getComments();
        $comments = $Comments->toArray();

        // look if the same comment already exists
        foreach ($comments as $comment) {
            if ($comment['message'] === $message) {
                return;
            }
        }

        $Comments->addComment($message);

        try {
            $Order->save(QUI::getUsers()->getSystemUser());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }
}
