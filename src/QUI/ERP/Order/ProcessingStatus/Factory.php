<?php

/**
 * This file contains QUI\ERP\Order\ProcessingStatus\Factory
 */

namespace QUI\ERP\Order\ProcessingStatus;

use QUI;

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
     * @param string|integer $id - processing ID
     * @param string $color - color of the status
     * @param array $title - title
     *
     * @throws Exception
     * @throws QUI\Exception
     * @todo permissions
     */
    public function createProcessingStatus($id, $color, array $title)
    {
        $list = Handler::getInstance()->getList();
        $id   = (int)$id;
        $data = [];

        if (isset($list[$id])) {
            throw new Exception([
                'quiqqer/order',
                'exception.processStatus.exists'
            ]);
        }

        // config
        $Package = QUI::getPackage('quiqqer/order');
        $Config  = $Package->getConfig();

        $Config->setValue('processing_status', $id, $color);
        $Config->save();

        // translations
        if (\is_array($title)) {
            $languages = QUI::availableLanguages();

            foreach ($languages as $language) {
                if (isset($title[$language])) {
                    $data[$language] = $title[$language];
                }
            }
        }

        // ProcessingSatus title
        $data['package']  = 'quiqqer/order';
        $data['datatype'] = 'php,js';
        $data['html']     = 1;

        QUI\Translator::addUserVar(
            'quiqqer/order',
            'processing.status.'.$id,
            $data
        );

        QUI\Translator::publish('quiqqer/order');

        // Create translations for auto-notification
        Handler::getInstance()->createNotificationTranslations($id);
    }

    /**
     * Return a next ID to create a new Processing Status
     *
     * @return int
     */
    public function getNextId()
    {
        $list = Handler::getInstance()->getList();

        if (!\count($list)) {
            return 1;
        }

        $max = \max(\array_keys($list));

        return $max + 1;
    }
}
