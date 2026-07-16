<?php

/**
 * This file contains QUI\ERP\Order\ProcessingStatus\Handler
 */

namespace QUI\ERP\Order\ProcessingStatus;

use QUI;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Settings;

use function in_array;
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
     * @var array<int, string>|null
     */
    protected ?array $list = null;

    protected QUI\Config $OrderConfig;

    /**
     * @throws QUI\Exception
     */
    public function __construct()
    {
        $this->OrderConfig = Settings::getConfig();
    }

    /**
     * Return all processing status entries from the config
     *
     * @return array<int, string>
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
     * @return array<int, string>
     */
    public function refreshList(): array
    {
        $this->list = null;

        return $this->getList();
    }

    /**
     * Return the complete processing status objects
     *
     * @return list<Status>
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
     * @param int|string $id
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
        $this->OrderConfig->del('processing_status', (string)$Status->getId());
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
        $this->OrderConfig->setValue('processing_status_notification', (string)$Status->getId(), $notify ? "1" : "0");
        $this->OrderConfig->save();
    }

    /**
     * Update a processing status
     *
     * @param int|string $id
     * @param int|string $color
     * @param array<string, string> $title
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
        $this->OrderConfig->setValue('processing_status', (string)$Status->getId(), $color);
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

        $Locale = $Order->getCustomer()->getLocale();
        $mailerAttributes = [];
        $Project = $this->getProjectForCustomerMail($Order);

        if ($Project) {
            $mailerAttributes['Project'] = $Project;
        }

        $Mailer = new QUI\Mail\Mailer($mailerAttributes);

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

    /**
     * Resolve the best matching project for customer-facing order mails.
     */
    protected function getProjectForCustomerMail(AbstractOrder $Order): null | QUI\Projects\Project
    {
        $projectName = $Order->getAttribute('project_name') ?: false;
        $customerLang = false;

        try {
            $Customer = $Order->getCustomer();
            $customerLang = $Customer->getLang() ?: false;
        } catch (\Exception) {
        }

        if ($projectName) {
            try {
                $Project = QUI::getRewrite()->getProject();

                if (!$Project || $Project->getName() !== $projectName) {
                    $Project = QUI::getProjectManager()->getProject($projectName);
                }

                if ($customerLang && in_array($customerLang, $Project->getLanguages(), true)) {
                    return QUI::getProjectManager()->getProject($projectName, $customerLang);
                }

                return $Project;
            } catch (\Exception) {
            }
        }

        return QUI::getRewrite()->getProject() ?: null;
    }
}
