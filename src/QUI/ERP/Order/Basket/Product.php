<?php

/**
 * This file contains QUI\ERP\Order\Basket
 */

namespace QUI\ERP\Order\Basket;

use QUI;
use QUI\ERP\Products\Handler\Fields;
use QUI\ERP\Products\Product\UniqueProduct;

/**
 * Class Product
 *
 * @package QUI\ERP\Order\Basket
 */
class Product extends UniqueProduct
{
    /**
     * Product constructor.
     *
     * @param int $pid - Product ID
     * @param array $attributes
     *
     * @throws QUI\Exception
     * @throws QUI\ERP\Products\Product\Exception
     */
    public function __construct($pid, array $attributes = [])
    {
        $fields    = [];
        $fieldList = [];
        $Product   = QUI\ERP\Products\Handler\Products::getProduct($pid);

        if (isset($attributes['fields'])) {
            $fields = $attributes['fields'];
        }

        foreach ($fields as $fieldId => $fieldValue) {
            try {
                if (is_array($fieldValue) && isset($fieldValue['value'])) {
                    $Field = Fields::getField($fieldValue['id']);
                    $Field->setValue($fieldValue['value']);
                } elseif (Fields::isField($fieldValue)) {
                    /* @var $fieldValue QUI\ERP\Products\Interfaces\FieldInterface */
                    $Field = Fields::getField($fieldValue->getId());
                    $Field->setValue($fieldValue->getValue());
                } else {
                    $Field = Fields::getField($fieldId);
                    $Field->setValue($fieldValue);
                }
            } catch (QUI\Exception $Exception) {
                continue;
            } catch (\Exception $Exception) {
                QUI\System\Log::writeRecursive($fieldValue);
                QUI\System\Log::writeException($Exception);
                continue;
            }

            $fieldList[$Field->getId()] = $Field->getAttributesForUniqueField();
        }

        // fehlende fields setzen
        $productFields = $Product->getFields();

        /* @var $Field QUI\ERP\Products\Field\Field */
        foreach ($productFields as $Field) {
            if (!isset($fieldList[$Field->getId()])) {
                $fieldList[$Field->getId()] = $Field->getAttributesForUniqueField();
            }
        }

        $attributes['fields'] = array_values($fieldList);
        $attributes['uid']    = QUI::getUserBySession()->getId();

        parent::__construct($pid, $attributes);
    }
}
