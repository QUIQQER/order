<?php

/**
 * This file contains QUI\ERP\Order\Utils\Panel
 */

namespace QUI\ERP\Order\Utils;

use QUI;

/**
 * Panel Utils
 */
class Panel
{
    /**
     * Return all packages which have an order.xml
     *
     * @return array
     */
    public static function getOrderPackages(): array
    {
        $packages = QUI::getPackageManager()->getInstalled();
        $list = [];

        /* @var $Package QUI\Package\Package */
        foreach ($packages as $package) {
            try {
                $Package = QUI::getPackage($package['name']);
            } catch (QUI\Exception $Exception) {
                continue;
            }

            if (!$Package->isQuiqqerPackage()) {
                continue;
            }

            $dir = $Package->getDir();

            if (file_exists($dir . '/order.xml')) {
                $list[] = $Package;
            }
        }

        return $list;
    }

    /**
     * @return array|bool|object|string
     */
    public static function getPanelCategories()
    {
        $cache = 'package/quiqqer/order/panelCategories';

        try {
            return QUI\Cache\Manager::get($cache);
        } catch (QUI\Exception $exception) {
        }

        $result = [];
        $packages = self::getOrderPackages();

        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return [];
        }

        /** @var QUI\Package\Package $Package */
        foreach ($packages as $Package) {
            $Parser = new QUI\Utils\XML\Settings();
            $Parser->setXMLPath('//quiqqer/order/panel');

            $Collection = $Parser->getCategories($Package->getDir() . '/order.xml');

            foreach ($Collection as $entry) {
                $categoryName = $entry['name'];

                if (isset($result[$categoryName])) {
                    continue;
                }

                $result[$categoryName]['name'] = $entry['name'];
                $result[$categoryName]['title'] = $entry['title'];

                if (isset($entry['icon'])) {
                    $result[$categoryName]['icon'] = $entry['icon'];
                }
            }
        }

        try {
            QUI\Cache\Manager::set($cache, $result);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return $result;
    }

    /**
     * @param $category
     * @return string
     */
    public static function getPanelCategory($category): string
    {
        $packages = self::getOrderPackages();
        $files = [];

        $Parser = new QUI\Utils\XML\Settings();
        $Parser->setXMLPath('//quiqqer/order/panel');

        foreach ($packages as $Package) {
            $files[] = $Package->getDir() . '/order.xml';
        }

        return $Parser->getCategoriesHtml($files, $category);
    }
}
