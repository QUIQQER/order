<?php

/**
 * This file contains package_quiqqer_order_ajax_frontend_order_address_save
 */

/**
 * Save the address data to the address
 *
 * @param integer $addressId - Address ID
 * @param string $data - JSON data
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_order_address_save',
    function ($addressId, $data) {
        $User    = QUI::getUserBySession();
        $Address = $User->getAddress((int)$addressId);
        $data    = \json_decode($data, true);

        if ($Address === null) {
            return false;
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
            if (isset($data[$field])) {
                $Address->setAttribute($field, $data[$field]);
            }
        }

        if (isset($data['tel'])) {
            $Address->editPhone(0, $data['tel']);
        }

        if (isset($data['mobile'])) {
            $Address->editMobile($data['mobile']);
        }

        if (isset($data['fax'])) {
            $Address->editFax($data['fax']);
        }


        // user data
        $User = $Address->getUser();

        if (isset($data['businessType'])) {
            if ($data['businessType'] === 'b2b') {
                $User->setAttribute('quiqqer.erp.isNettoUser', QUI\ERP\Utils\User::IS_NETTO_USER);
            } else {
                $User->setAttribute('quiqqer.erp.isNettoUser', QUI\ERP\Utils\User::IS_BRUTTO_USER);
            }
        }

        $currentVat = $User->getAttribute('quiqqer.erp.euVatId');

        if (isset($data['vatId']) && empty($currentVat)) {
            $User->setAttribute('quiqqer.erp.euVatId', $data['vatId']);
        }

        $User->save();

        // check missing fields
        $missing = QUI\FrontendUsers\Utils::getMissingAddressFields($Address);

        if (\count($missing)) {
            throw new QUI\Exception([
                'quiqqer/frontend-users',
                'exception.controls.profile.address.required_fields_empty'
            ]);
        }

        $Address->save();

        try {
            QUI\ERP\Order\Controls\OrderProcess\CustomerData::validateAddress(
                $User->getAddress((int)$addressId)
            );

            QUI::getMessagesHandler()->addSuccess(
                QUI::getLocale()->get(
                    'quiqqer/order',
                    'message.address.saved.successfully'
                )
            );

            return true;
        } catch (QUI\ERP\Order\Exception $Exception) {
            QUI::getMessagesHandler()->addAttention(
                $Exception->getMessage()
            );

            return false;
        }
    },
    ['addressId', 'data']
);
