<?php

namespace QUITests\ERP\Order\Fixtures;

use QUI;
use QUI\ERP\Areas\Area;
use QUI\ERP\Areas\Handler as AreasHandler;
use QUI\Permissions\Permission;
use ReflectionProperty;
use RuntimeException;
use Throwable;

final class DefaultAreaEnvironment
{
    private static bool $cleanupRegistered = false;
    private static bool $ready = false;
    private static bool $defaultAreaChanged = false;
    private static mixed $originalDefaultArea = null;
    private static ?int $createdAreaId = null;

    public static function ensure(): void
    {
        if (self::$ready) {
            return;
        }

        self::registerCleanup();

        try {
            QUI\ERP\Defaults::getArea();
            self::$ready = true;

            return;
        } catch (Throwable) {
        }

        try {
            self::runAsSystemUser(static function (): void {
                $Config = QUI::getPackage('quiqqer/tax')->getConfig();

                if ($Config === null) {
                    throw new RuntimeException('The tax configuration is not available.');
                }

                self::$originalDefaultArea = $Config->getValue('shop', 'area');
                self::$defaultAreaChanged = true;
                $Country = QUI\ERP\Defaults::getCountry();
                $Area = (new AreasHandler())->createChild([
                    'countries' => $Country->getCode(),
                    'data' => json_encode([
                        'importLocale' => 'PHPUnit Order default area'
                    ], JSON_THROW_ON_ERROR)
                ]);

                if (!$Area instanceof Area) {
                    throw new RuntimeException('The default area could not be created.');
                }

                self::$createdAreaId = (int)$Area->getId();
                $Config->set('shop', 'area', (string)self::$createdAreaId);
                $Config->save();
            });
            self::$ready = true;
        } catch (Throwable $Exception) {
            self::cleanup();

            throw new RuntimeException(
                'The PHPUnit Order area environment could not be created: ' . $Exception->getMessage(),
                0,
                $Exception
            );
        }
    }

    public static function cleanup(): void
    {
        if (!self::$defaultAreaChanged && self::$createdAreaId === null) {
            return;
        }

        try {
            self::runAsSystemUser(static function (): void {
                self::restoreDefaultArea();
                self::deleteCreatedArea();
            });
        } catch (Throwable) {
            self::restoreDefaultAreaSafely();
            self::deleteCreatedAreaDirectly();
        } finally {
            self::$ready = false;
            self::$defaultAreaChanged = false;
            self::$originalDefaultArea = null;
            self::$createdAreaId = null;
        }
    }

    private static function restoreDefaultArea(): void
    {
        if (!self::$defaultAreaChanged) {
            return;
        }

        $Config = QUI::getPackage('quiqqer/tax')->getConfig();

        if ($Config === null) {
            return;
        }

        if (self::$originalDefaultArea === false || self::$originalDefaultArea === null) {
            $Config->del('shop', 'area');
        } else {
            $Config->set('shop', 'area', self::$originalDefaultArea);
        }

        $Config->save();
    }

    private static function restoreDefaultAreaSafely(): void
    {
        try {
            self::restoreDefaultArea();
        } catch (Throwable) {
        }
    }

    private static function deleteCreatedArea(): void
    {
        if (self::$createdAreaId === null) {
            return;
        }

        (new AreasHandler())->getChild(self::$createdAreaId)->delete();
    }

    private static function deleteCreatedAreaDirectly(): void
    {
        if (self::$createdAreaId === null) {
            return;
        }

        try {
            QUI::getDataBaseConnection()->delete(
                QUI::getDBTableName('areas'),
                ['id' => self::$createdAreaId]
            );
        } catch (Throwable) {
        }
    }

    private static function runAsSystemUser(callable $Callback): mixed
    {
        $PermissionUser = new ReflectionProperty(Permission::class, 'User');
        $previousPermissionUser = $PermissionUser->getValue();
        Permission::setUser(QUI::getUsers()->getSystemUser());

        try {
            return $Callback();
        } finally {
            $PermissionUser->setValue(null, $previousPermissionUser);
        }
    }

    private static function registerCleanup(): void
    {
        if (self::$cleanupRegistered) {
            return;
        }

        self::$cleanupRegistered = true;

        if (class_exists(QUI\System\TestCleanup::class)) {
            QUI\System\TestCleanup::register();
            QUI::getEvents()->addEvent(
                QUI\System\TestCleanup::EVENT,
                static function (): void {
                    self::cleanup();
                }
            );

            return;
        }

        register_shutdown_function(static function (): void {
            self::cleanup();
        });
    }
}
