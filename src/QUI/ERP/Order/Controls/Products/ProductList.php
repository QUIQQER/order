<?php

/**
 * This file contains QUI\ERP\Order\Controls\Products\ProductList
 */

namespace QUI\ERP\Order\Controls\Products;

use QUI;

/**
 * Class ProductList
 *
 * @package QUI\ERP\Order\Products
 */
class ProductList extends QUI\Control
{
    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $this->setAttribute('nodeName', 'section');
        $this->setAttribute('nodeName', 'section');

        $Engine     = QUI::getTemplateManager()->getEngine();
        $productIds = $this->getAttribute('productsIds');
        $products   = [];

        QUI\System\Log::writeRecursive('#####');
        QUI\System\Log::writeRecursive($productIds);

        if (!is_array($productIds)) {
            $productIds = json_decode($productIds, true);
        }

        if (is_array($productIds)) {
            foreach ($productIds as $productId) {
                try {
                    $Product    = QUI\ERP\Products\Handler\Products::getProduct($productId);
                    $products[] = $Product->getView();
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }
            }
        }

        $Engine->assign([
            'products' => $products
        ]);

        return $Engine->fetch(dirname(__FILE__).'/ProductList.html');
    }
}
