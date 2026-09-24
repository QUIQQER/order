<?php

namespace QUI\ERP\Order\ProcessingStatus;

use QUI\Locale;

/**
 * Recover missing status titles without overwriting customer translations.
 */
class TitleMigration
{
    /**
     * @param array<string, mixed> $translation
     * @param array<string> $languages
     * @return array<string, string>
     */
    public static function getUpdates(
        int | string $statusId,
        array $translation,
        array $languages,
        Locale $Locale
    ): array {
        $updates = [];

        foreach ($languages as $language) {
            $original = (string)($translation[$language] ?? '');
            $edited = (string)($translation[$language . '_edit'] ?? '');
            $title = $edited !== '' && $edited !== '0' ? $edited : $original;

            if (trim($title) !== '' && !$Locale->isLocaleString($title)) {
                continue;
            }

            // An invalid override must not hide an existing, meaningful base translation.
            if (trim($original) !== '' && !$Locale->isLocaleString($original)) {
                $replacement = $original;
            } elseif ($Locale->isLocaleString($title)) {
                [$group, $key] = $Locale->getPartsOfLocaleString($title);

                if ($group === null || $key === null) {
                    continue;
                }

                $replacement = $Locale->getByLang($language, $group, $key);
            } elseif (in_array((int)$statusId, [1, 2, 3, 4, 5], true)) {
                $replacement = $Locale->getByLang(
                    $language,
                    'quiqqer/order',
                    'processing.status.default.' . $statusId
                );
            } else {
                // Custom statuses have no known default title.
                continue;
            }

            if (trim($replacement) === '' || $Locale->isLocaleString($replacement)) {
                continue;
            }

            $updates[$language . '_edit'] = $replacement;
        }

        return $updates;
    }
}
