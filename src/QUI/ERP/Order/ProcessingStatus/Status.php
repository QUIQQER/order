<?php

/**
 * This file contains QUI\ERP\Order\ProcessingStatus\Status
 */

namespace QUI\ERP\Order\ProcessingStatus;

use QUI;

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
    protected $id;

    /**
     * @var string
     */
    protected $color;

    /**
     * Status constructor.
     *
     * @param int|\string $id - Processing status id
     * @throws Exception
     */
    public function __construct($id)
    {
        $list = Handler::getInstance()->getList();

        if (!isset($list[$id])) {
            throw new Exception([
                'quiqqer/order',
                'exception.processingStatus.not.found'
            ]);
        }

        $this->id    = (int)$id;
        $this->color = $list[$id];
    }

    //region Getter

    /**
     * Return the status id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Return the title
     *
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getTitle($Locale = null)
    {
        if (!($Locale instanceof QUI\Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/order', 'processing.status.'.$this->id);
    }

    /**
     * Return the status color
     *
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    //endregion

    /**
     * Status as array
     *
     * @param null|QUI\Locale $Locale - optional. if no locale, all translations would be returned
     * @return array
     */
    public function toArray($Locale = null)
    {
        $title = $this->getTitle($Locale);

        if ($Locale === null) {
            $title     = [];
            $Locale    = QUI::getLocale();
            $languages = QUI::availableLanguages();

            foreach ($languages as $language) {
                $title[$language] = $Locale->getByLang(
                    $language,
                    'quiqqer/order',
                    'processing.status.'.$this->getId()
                );
            }
        }

        return [
            'id'    => $this->getId(),
            'title' => $title,
            'color' => $this->getColor()
        ];
    }
}
