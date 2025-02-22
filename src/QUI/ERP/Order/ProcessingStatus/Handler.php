<?php

/**
 * This file contains QUI\ERP\Order\ProcessingStatus\Handler
 */

namespace QUI\ERP\Order\ProcessingStatus;

use QUI;
use QUI\ERP\Order\AbstractOrder;

use function is_array;

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
     * @var ?array
     */
    protected ?array $list = null;

    protected QUI\Config $OrderConfig;

    /**
     * @throws QUI\Exception
     */
    public function __construct()
    {
        $this->OrderConfig = QUI::getPackage('quiqqer/order')->getConfig();
    }

    /**
     * Return all processing status entries from the config
     *
     * @return array
     */
    public function getList(): array
    {
        if ($this->list !== null) {
            return $this->list;
        }

        $result = $this->OrderConfig->getSection('processing_status');

        if (!$result || !is_array($result)) {
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
    public function refreshList(): array
    {
        $this->list = null;

        return $this->getList();
    }

    /**
     * Return the complete processing status objects
     *
     * @return Status[]
     */
    public function getProcessingStatusList(): array
    {
        $list = $this->getList();
        $result = [];

        foreach ($list as $entry => $color) {
            try {
                $result[] = $this->getProcessingStatus($entry);
            } catch (Exception) {
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
    public function getProcessingStatus($id): StatusUnknown | Status
    {
        if ($id === 0) {
            return new StatusUnknown();
        }

        return new Status($id);
    }

    /**
     * Get defined "cancelled" order status.
     *
     * @return Status|StatusUnknown
     * @throws Exception
     */
    public function getCancelledStatus(): Status | StatusUnknown
    {
        $cancelledStatusId = $this->OrderConfig->get('orderStatus', 'cancelled');

        if (empty($cancelledStatusId)) {
            return new StatusUnknown();
        }

        return $this->getProcessingStatus($cancelledStatusId);
    }

    /**
     * Delete / Remove a processing status
     *
     * @param int|string $id
     *
     * @throws Exception
     * @throws QUI\Exception
     *
     * @todo permissions
     */
    public function deleteProcessingStatus(int | string $id): void
    {
        $Status = $this->getProcessingStatus($id);

        // remove translation
        QUI\Translator::delete(
            'quiqqer/order',
            'processing.status.' . $Status->getId()
        );

        QUI\Translator::publish('quiqqer/order');

        // update config
        $this->OrderConfig->del('processing_status', $Status->getId());
        $this->OrderConfig->save();
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
    public function setProcessingStatusNotification(int $id, bool $notify): void
    {
        $Status = $this->getProcessingStatus($id);

        // update config
        $this->OrderConfig->setValue('processing_status_notification', $Status->getId(), $notify ? "1" : "0");
        $this->OrderConfig->save();
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
    public function updateProcessingStatus(int | string $id, int | string $color, array $title): void
    {
        $Status = $this->getProcessingStatus($id);

        // update translation
        $languages = QUI::availableLanguages();

        $data = [
            'package' => 'quiqqer/order',
            'datatype' => 'php,js',
            'html' => 1
        ];

        foreach ($languages as $language) {
            if (isset($title[$language])) {
                $data[$language] = $title[$language];
                $data[$language . '_edit'] = $title[$language];
            }
        }

        QUI\Translator::edit(
            'quiqqer/order',
            'processing.status.' . $Status->getId(),
            'quiqqer/order',
            $data
        );

        QUI\Translator::publish('quiqqer/order');

        // update config
        $this->OrderConfig->setValue('processing_status', $Status->getId(), $color);
        $this->OrderConfig->save();
    }

    /**
     * Create translations for status notification
     *
     * @param int $id
     * @return void
     */
    public function createNotificationTranslations(int $id): void
    {
        $data = [
            'package' => 'quiqqer/order',
            'datatype' => 'php,js',
            'html' => 1
        ];

        // translations
        $L = new QUI\Locale();
        $languages = QUI::availableLanguages();

        foreach ($languages as $language) {
            $L->setCurrent($language);
            $data[$language] = $L->get('quiqqer/order', 'processing.status.notification.template');
        }

        try {
            // Check if translation already exists
            $translation = QUI\Translator::get('quiqqer/order', 'processing.status.notification.' . $id);

            if (!empty($translation)) {
                return;
            }

            // ProcessingStatus notification messages
            QUI\Translator::addUserVar(
                'quiqqer/order',
                'processing.status.notification.' . $id,
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
     * @param string|null $message (optional) - Custom notification message [default: default status change message]
     * @return void
     *
     * @throws QUI\Exception
     */
    public function sendStatusChangeNotification(
        AbstractOrder $Order,
        int $statusId,
        null | string $message = null
    ): void {
        $Customer = $Order->getCustomer();
        $customerEmail = $Customer->getAttribute('email');

        if (empty($customerEmail)) {
            QUI\System\Log::addWarning(
                'Status change notification for order #' . $Order->getPrefixedNumber() . ' cannot be sent'
                . ' because customer #' . $Customer->getUUID() . ' has no e-mail address.'
            );

            return;
        }

        if (empty($message)) {
            $Status = $this->getProcessingStatus($statusId);
            $message = $Status->getStatusChangeNotificationText($Order);
        }

        $Mailer = new QUI\Mail\Mailer();
        $Locale = $Order->getCustomer()->getLocale();

        $Mailer->setSubject(
            $Locale->get('quiqqer/order', 'processing.status.notification.subject', [
                'orderNo' => $Order->getPrefixedNumber()
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
