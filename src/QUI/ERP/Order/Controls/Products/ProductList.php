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
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setAttribute('class', 'quiqqer-order-productList');
        $this->setAttribute('nodeName', 'section');
        $this->addCSSFile(\dirname(__FILE__).'/ProductList.css');
    }

    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $Engine     = QUI::getTemplateManager()->getEngine();
        $productIds = $this->getAttribute('productsIds');
        $products   = [];

        if (!\is_array($productIds)) {
            $productIds = \json_decode($productIds, true);
        }

        if (\is_array($productIds)) {
            foreach ($productIds as $productId) {
                try {
                    $Product    = QUI\ERP\Products\Handler\Products::getProduct((int)$productId);
                    $products[] = $Product->getView();
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }
            }
        }

        $Engine->assign([
            'products' => $products
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/ProductList.html');
    }
}
