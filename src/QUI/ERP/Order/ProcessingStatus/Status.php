<?php

/**
 * This file contains QUI\ERP\Order\ProcessingStatus\Status
 */

namespace QUI\ERP\Order\ProcessingStatus;

use QUI;
use QUI\ERP\Order\AbstractOrder;

use function boolval;

/**
 * Class Exception
 *
 * @package QUI\ERP\Order\ProcessingStatus
 */
class Status
{
    /**
     * @var int
     */
    protected int $id;

    /**
     * @var string
     */
    protected mixed $color;

    /**
     * @var bool
     */
    protected bool $notification = false;

    /**
     * Status constructor.
     *
     * @param int|string $id - Processing status id
     * @throws Exception
     */
    public function __construct(int|string $id)
    {
        $list = Handler::getInstance()->getList();

        if (!isset($list[$id])) {
            throw new Exception([
                'quiqqer/order',
                'exception.processingStatus.not.found'
            ]);
        }

        $this->id = (int)$id;
        $this->color = $list[$id];

        // notification
        try {
            $Package = QUI::getPackage('quiqqer/order');
            $Config = $Package->getConfig();
            $result = $Config->getSection('processing_status_notification');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        if (!empty($result[$id])) {
            $this->notification = boolval($result[$id]);
        }
    }

    //region Getter

    /**
     * Return the status id
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Return the title
     *
     * @param null|QUI\Locale $Locale (optional) $Locale
     * @return string
     */
    public function getTitle(QUI\Locale $Locale = null): string
    {
        if (!($Locale instanceof QUI\Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/order', 'processing.status.' . $this->id);
    }

    /**
     * Get status notification message
     *
     * @param AbstractOrder $Order - The order the status change applies to
     * @param QUI\Locale|null $Locale (optional) - [default: QUI::getLocale()]
     * @return string
     */
    public function getStatusChangeNotificationText(AbstractOrder $Order, QUI\Locale $Locale = null): string
    {
        if (!($Locale instanceof QUI\Locale)) {
            $Locale = QUI::getLocale();
        }

        $Customer = $Order->getCustomer();
        $message = $Locale->get('quiqqer/order', 'processing.status.notification.' . $this->id, [
            'customerName' => $Customer->getName(),
            'orderNo' => $Order->getPrefixedId(),
            'orderDate' => $Locale->formatDate($Order->getCreateDate()),
            'orderStatus' => $this->getTitle($Locale)
        ]);

        if (QUI::getLocale()->isLocaleString($message)) {
            $message = $Locale->get('quiqqer/order', 'processing.status.notification.template', [
                'customerName' => $Customer->getName(),
                'orderNo' => $Order->getPrefixedId(),
                'orderDate' => $Locale->formatDate($Order->getCreateDate()),
                'orderStatus' => $this->getTitle($Locale)
            ]);
        }

        return $message;
    }

    /**
     * Return the status color
     *
     * @return string
     */
    public function getColor(): string
    {
        return $this->color;
    }

    /**
     * Check if the customer has to be notified if this status is set to an order
     *
     * @return bool
     */
    public function isAutoNotification(): bool
    {
        return $this->notification;
    }

    //endregion

    /**
     * Status as array
     *
     * @param null|QUI\Locale $Locale - optional. if no locale, all translations would be returned
     * @return array
     */
    public function toArray(QUI\Locale $Locale = null): array
    {
        $title = $this->getTitle($Locale);
        $statusChangeText = [];

        if ($Locale === null) {
            $statusId = $this->getId();
            $title = [];

            $Locale = QUI::getLocale();
            $languages = QUI::availableLanguages();

            foreach ($languages as $language) {
                $title[$language] = $Locale->getByLang(
                    $language,
                    'quiqqer/order',
                    'processing.status.' . $statusId
                );

                $statusChangeText[$language] = $Locale->getByLang(
                    $language,
                    'quiqqer/order',
                    'processing.status.notification.' . $statusId
                );
            }
        }

        return [
            'id' => $this->getId(),
            'title' => $title,
            'color' => $this->getColor(),
            'notification' => $this->isAutoNotification(),
            'statusChangeText' => $statusChangeText
        ];
    }
}
