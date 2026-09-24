<?php

namespace QUITests\ERP\Order\ProcessingStatus;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\ProcessingStatus\TitleMigration;
use QUI\Locale;

class TitleMigrationTest extends TestCase
{
    public function testMissingDefaultTranslationsAreFilledAndCustomNamesPreserved(): void
    {
        $Locale = $this->getMockBuilder(Locale::class)->onlyMethods(['getByLang'])->getMock();
        $Locale->expects(self::exactly(5))->method('getByLang')->willReturnCallback(
            static function (string $language, string $group, string $key): string {
                self::assertSame('en', $language);
                self::assertSame('quiqqer/order', $group);

                return 'Default ' . $key;
            }
        );

        foreach ([1, 2, 3, 4, 5] as $id) {
            $row = ['de' => null, 'de_edit' => 'Mein Status', 'en' => null, 'en_edit' => null];
            $updates = TitleMigration::getUpdates($id, $row, ['de', 'en'], $Locale);

            self::assertSame(['en_edit' => 'Default processing.status.default.' . $id], $updates);
            self::assertSame([], TitleMigration::getUpdates($id, array_replace($row, $updates), ['de', 'en'], $Locale));
        }
    }

    public function testBaseTranslationsAndUserOverridesAreKept(): void
    {
        $Locale = $this->getMockBuilder(Locale::class)->onlyMethods(['getByLang'])->getMock();
        $Locale->expects(self::never())->method('getByLang');

        self::assertSame([], TitleMigration::getUpdates(1, [
            'de' => 'Offen', 'de_edit' => '', 'en' => 'Open', 'en_edit' => 'Waiting for approval'
        ], ['de', 'en'], $Locale));
    }

    public function testStoredGuestOrderPlaceholdersAreResolvedPerLanguage(): void
    {
        $Locale = $this->getMockBuilder(Locale::class)->onlyMethods(['getByLang'])->getMock();
        $Locale->expects(self::exactly(2))->method('getByLang')->willReturnMap([
            ['de', 'quiqqer/order-guestorder', 'processing.status.new.guest.order', 'Neue Gastbestellung'],
            ['en', 'quiqqer/order-guestorder', 'processing.status.new.guest.order', 'New guest order']
        ]);
        $placeholder = '[quiqqer/order-guestorder] processing.status.new.guest.order';
        $row = ['de_edit' => $placeholder, 'en' => $placeholder];
        $updates = TitleMigration::getUpdates(42, $row, ['de', 'en'], $Locale);

        self::assertSame(['de_edit' => 'Neue Gastbestellung', 'en_edit' => 'New guest order'], $updates);
        self::assertSame([], TitleMigration::getUpdates(42, array_replace($row, $updates), ['de', 'en'], $Locale));
    }

    public function testInvalidOverrideRestoresExistingBaseTranslation(): void
    {
        $Locale = $this->getMockBuilder(Locale::class)->onlyMethods(['getByLang'])->getMock();
        $Locale->expects(self::never())->method('getByLang');

        self::assertSame(['en_edit' => 'Custom title'], TitleMigration::getUpdates(1, [
            'en' => 'Custom title', 'en_edit' => '[quiqqer/order] processing.status.default.1'
        ], ['en'], $Locale));
    }

    public function testUnavailableSourceTranslationsAreNotWritten(): void
    {
        $Locale = $this->getMockBuilder(Locale::class)->onlyMethods(['getByLang'])->getMock();
        $Locale->method('getByLang')->willReturnMap([
            ['de', 'quiqqer/order', 'processing.status.default.1', ''],
            ['en', 'quiqqer/order', 'processing.status.default.1', '[quiqqer/order] processing.status.default.1'],
            ['en', 'quiqqer/missing', 'status', '[quiqqer/missing] status']
        ]);

        self::assertSame([], TitleMigration::getUpdates(1, [], ['de', 'en'], $Locale));
        self::assertSame([], TitleMigration::getUpdates(6, ['en_edit' => '[quiqqer/missing] status'], ['en'], $Locale));
    }

    public function testEmptyCustomStatusNamesHaveNoGuessedDefault(): void
    {
        $Locale = $this->getMockBuilder(Locale::class)->onlyMethods(['getByLang'])->getMock();
        $Locale->expects(self::never())->method('getByLang');

        self::assertSame([], TitleMigration::getUpdates(6, [], ['de', 'en'], $Locale));
    }
}
