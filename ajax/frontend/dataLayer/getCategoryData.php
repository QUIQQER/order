<?php

QUI::$Ajax->registerFunction(
    'package_quiqqer_order_ajax_frontend_dataLayer_getCategoryData',
    function ($project, $siteId) {
        try {
            $Project = QUI::getProjectManager()->decode($project);
            $Site = $Project->get($siteId);
            $categoryId = $Site->getAttribute('quiqqer.products.settings.categoryId');

            $Category = QUI\ERP\Products\Handler\Categories::getCategory($categoryId);

            return $Category->getTitle();
        } catch (QUI\Exception $Exception) {
            return '';
        }
    },
    ['project', 'siteId']
);
