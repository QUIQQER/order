<?php

/**
 * This file contains QUI\ERP\Order\ProcessingStatus\Factory
 */

namespace QUI\ERP\Order\ProcessingStatus;

use QUI;

use function array_keys;
use function count;
use function max;

/**
 * Class Factory
 * - For processing status creation
 *
 * @package QUI\ERP\Order\ProcessingStatus\Factory
 */
class Factory extends QUI\Utils\Singleton
{
    /**
     * Create a new processing status
     *
     * @param integer|string $id - processing ID
     * @param string $color - color of the status
     * @param array $title - title
     *
     * @throws Exception
     * @throws QUI\Exception
     * @todo permissions
     */
    public function createProcessingStatus(int|string $id, string $color, array $title): void
    {
        $list = Handler::getInstance()->getList();
        $id = (int)$id;
        $data = [];

        if (isset($list[$id])) {
            throw new Exception([
                'quiqqer/order',
                'exception.processStatus.exists'
            ]);
        }

        // config
        $Package = QUI::getPackage('quiqqer/order');
        $Config = $Package->getConfig();

        $Config->setValue('processing_status', $id, $color);
        $Config->save();

        // translations
        $languages = QUI::availableLanguages();

        foreach ($languages as $language) {
            if (isset($title[$language])) {
                $data[$language] = $title[$language];
            }
        }

        // Processing status title
        $data['package'] = 'quiqqer/order';
        $data['datatype'] = 'php,js';
        $data['html'] = 1;

        QUI\Translator::addUserVar(
            'quiqqer/order',
            'processing.status.' . $id,
            $data
        );

        QUI\Translator::publish('quiqqer/order');

        // Create translations for auto-notification
        Handler::getInstance()->createNotificationTranslations($id);
        Handler::getInstance()->refreshList();
    }

    /**
     * Return a next ID to create a new Processing Status
     *
     * @return int
     */
    public function getNextId(): int
    {
        $list = Handler::getInstance()->getList();

        if (!count($list)) {
            return 1;
        }

        $max = max(array_keys($list));

        return $max + 1;
    }
}
