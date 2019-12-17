<?php

/**
 * This file contains QUI\ERP\Order\ProcessingStatus\Handler
 */

namespace QUI\ERP\Order\ProcessingStatus;

use QUI;
use QUI\ERP\Order\AbstractOrder;

/**
 * Class Handler
 * - Processing status management
 * - Returns processing status objects
 * - Returns processing status lists
 *
 * @package QUI\ERP\Order\ProcessingStatus\Factory
 */
class Handler extends QUI\Utils\Singleton
{
    /**
     * @var array
     */
    protected $list = null;

    /**
     * Return all processing status entries from the config
     *
     * @return array
     */
    public function getList()
    {
        if ($this->list !== null) {
            return $this->list;
        }

        try {
            $Package = QUI::getPackage('quiqqer/order');
            $Config  = $Package->getConfig();
            $result  = $Config->getSection('processing_status');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return [];
        }

        if (!$result || !\is_array($result)) {
            $this->list = [];

            return $this->list;
        }

        $this->list = $result;

        return $result;
    }

    /**
     * Refresh the internal list
     *
     * @return array
     */
    public function refreshList()
    {
        $this->list = null;

        return $this->getList();
    }

    /**
     * Return the complete processing status objects
     *
     * @return Status[]
     */
    public function getProcessingStatusList()
    {
        $list   = $this->getList();
        $result = [];

        foreach ($list as $entry => $color) {
            try {
                $result[] = $this->getProcessingStatus($entry);
            } catch (Exception $Exception) {
            }
        }

        return $result;
    }

    /**
     * Return a processing status
     *
     * @param $id
     * @return Status|StatusUnknown
     *
     * @throws Exception
     */
    public function getProcessingStatus($id)
    {
        if ($id === 0) {
            return new StatusUnknown();
        }

        return new Status($id);
    }

    /**
     * Delete / Remove a processing status
     *
     * @param string|int $id
     *
     * @throws Exception
     * @throws QUI\Exception
     *
     * @todo permissions
     */
    public function deleteProcessingStatus($id)
    {
        $Status = $this->getProcessingStatus($id);

        // remove translation
        QUI\Translator::delete(
            'quiqqer/order',
            'processing.status.'.$Status->getId()
        );

        QUI\Translator::publish('quiqqer/order');

        // update config
        $Package = QUI::getPackage('quiqqer/order');
        $Config  = $Package->getConfig();

        $Config->del('processing_status', $Status->getId());
        $Config->save();
    }

    /**
     * Set auto-notification setting for a status
     *
     * @param int $id - ProcessingStatus ID
     * @param bool $notify - Auto-notification if an order is changed to the given status?
     * @return void
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function setProcessingStatusNotification($id, $notify)
    {
        $Status = $this->getProcessingStatus($id);

        // update config
        $Package = QUI::getPackage('quiqqer/order');
        $Config  = $Package->getConfig();

        $Config->setValue('processing_status_notification', $Status->getId(), $notify ? "1" : "0");
        $Config->save();
    }

    /**
     * Update a processing status
     *
     * @param int|string $id
     * @param int|string $color
     * @param array $title
     *
     * @throws QUI\Exception
     *
     * @todo permissions
     */
    public function updateProcessingStatus($id, $color, array $title)
    {
        $Status = $this->getProcessingStatus($id);

        // update translation
        $languages = QUI::availableLanguages();

        $data = [
            'package'  => 'quiqqer/order',
            'datatype' => 'php,js',
            'html'     => 1
        ];

        foreach ($languages as $language) {
            if (isset($title[$language])) {
                $data[$language]         = $title[$language];
                $data[$language.'_edit'] = $title[$language];
            }
        }

        QUI\Translator::edit(
            'quiqqer/order',
            'processing.status.'.$Status->getId(),
            'quiqqer/order',
            $data
        );

        QUI\Translator::publish('quiqqer/order');

        // update config
        $Package = QUI::getPackage('quiqqer/order');
        $Config  = $Package->getConfig();

        $Config->setValue('processing_status', $Status->getId(), $color);
        $Config->save();
    }

    /**
     * Create translations for status notification
     *
     * @param int $id
     * @return void
     */
    public function createNotificationTranslations($id)
    {
        $data = [
            'package'  => 'quiqqer/order',
            'datatype' => 'php,js',
            'html'     => 1
        ];

        // translations
        $L         = new QUI\Locale();
        $languages = QUI::availableLanguages();

        foreach ($languages as $language) {
            $L->setCurrent($language);
            $data[$language] = $L->get('quiqqer/order', 'processing.status.notification.template');
        }

        try {
            // Check if transaltion already exists
            $translation = QUI\Translator::get('quiqqer/order', 'processing.status.notification.'.$id);

            if (!empty($translation)) {
                return;
            }

            // ProcessingStatus notification messages
            QUI\Translator::addUserVar(
                'quiqqer/order',
                'processing.status.notification.'.$id,
                $data
            );

            QUI\Translator::publish('quiqqer/order');
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Notify customer about an Order status change (via e-mail)
     *
     * @param AbstractOrder $Order
     * @param int $statusId
     * @param string $message (optional) - Custom notification message [default: default status change message]
     * @return void
     *
     * @throws QUI\Exception
     */
    public function sendStatusChangeNotification(AbstractOrder $Order, $statusId, $message = null)
    {
        $Customer      = $Order->getCustomer();
        $customerEmail = $Customer->getAttribute('email');

        if (empty($customerEmail)) {
            QUI\System\Log::addWarning(
                'Status change notification for order #'.$Order->getPrefixedId().' cannot be sent'
                .' because customer #'.$Customer->getId().' has no e-mail address.'
            );

            return;
        }

        if (empty($message)) {
            $Status  = $this->getProcessingStatus($statusId);
            $message = $Status->getStatusChangenNotificationText($Order);
        }

        $Mailer = new QUI\Mail\Mailer();
        /** @var QUI\Locale $Locale */
        $Locale = $Order->getCustomer()->getLocale();

        $Mailer->setSubject(
            $Locale->get('quiqqer/order', 'processing.status.notification.subject', [
                'orderNo' => $Order->getPrefixedId()
            ])
        );

        $Mailer->setBody($message);
        $Mailer->addRecipient($customerEmail);

        try {
            $Mailer->send();
            $Order->addStatusMail($message);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }
}
