<?php

/**
 * This file contains QUI\ERP\Order\Basket
 */

namespace QUI\ERP\Order\Basket;

use QUI;
use QUI\ERP\Products\Field\UniqueField;
use QUI\ERP\Products\Handler\Fields;
use QUI\ERP\Products\Product\UniqueProduct;

use function array_values;
use function is_array;

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
    public function __construct(int $pid, array $attributes = [])
    {
        $fields = [];
        $fieldList = [];
        $Product = QUI\ERP\Products\Handler\Products::getProduct($pid);

        $this->maximumQuantity = $Product->getMaximumQuantity();

        if (isset($attributes['fields'])) {
            $fields = $attributes['fields'];
        }

        foreach ($fields as $fieldId => $fieldValue) {
            if (isset($fieldValue['id'])) {
                $fieldId = $fieldValue['id'];
            }

            $Field = $this->importFieldData($fieldId, $fieldValue);

            if ($Field instanceof UniqueField) {
                $fieldList[$Field->getId()] = $Field->getAttributes();
            } elseif ($Field) {
                $fieldList[$Field->getId()] = $Field->getAttributesForUniqueField();
            }
        }

        if (isset($attributes['description'])) {
            $current = QUI::getLocale()->getCurrent();

            try {
                $Field = Fields::getField(Fields::FIELD_SHORT_DESC);
                $Field->setValue([
                    $current => $attributes['description']
                ]);

                $fieldList[$Field->getId()] = $Field->getAttributesForUniqueField();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        if (isset($attributes['customFields'])) {
            foreach ($attributes['customFields'] as $fieldId => $fieldValue) {
                if (!is_numeric($fieldId)) {
                    continue;
                }

                $Field = $this->importFieldData($fieldId, $fieldValue);

                if ($Field instanceof UniqueField) {
                    $fieldList[$Field->getId()] = $Field->getAttributes();
                } elseif ($Field) {
                    $fieldList[$Field->getId()] = $Field->getAttributesForUniqueField();
                }
            }
        }

        // set missing fields
        $productFields = $Product->getFields();

        foreach ($productFields as $Field) {
            if (!isset($fieldList[$Field->getId()]) && method_exists($Field, 'getAttributesForUniqueField')) {
                $fieldList[$Field->getId()] = $Field->getAttributesForUniqueField();
            }
        }

        $attributes['fields'] = array_values($fieldList);
        $attributes['uid'] = QUI::getUserBySession()->getUUID();

        parent::__construct($pid, $attributes);
    }

    /**
     * @param $fieldId
     * @param $fieldValue
     * @return null|QUI\ERP\Products\Field\Field|UniqueField
     */
    protected function importFieldData($fieldId, $fieldValue): QUI\ERP\Products\Field\Field | UniqueField | null
    {
        try {
            if (is_array($fieldValue) && isset($fieldValue['identifier'])) {
                return new UniqueField($fieldValue['identifier'], $fieldValue);
            }

            if (is_array($fieldValue) && isset($fieldValue['value'])) {
                $Field = Fields::getField($fieldValue['id']);
                $Field->setValue($fieldValue['value']);

                if (!empty($fieldValue['userinput'])) {
                    $Field->setValue(json_encode([
                        $fieldValue['value'],
                        $fieldValue['userinput']
                    ]));
                }
            } elseif (Fields::isField($fieldValue)) {
                /* @var $fieldValue QUI\ERP\Products\Interfaces\FieldInterface */
                $Field = Fields::getField($fieldValue->getId());
                $Field->setValue($fieldValue->getValue());
            } else {
                $Field = Fields::getField($fieldId);
                $Field->setValue($fieldValue);
            }
        } catch (QUI\Exception) {
            return null;
        } catch (\Exception $Exception) {
            QUI\System\Log::writeRecursive($fieldValue);
            QUI\System\Log::writeException($Exception);

            return null;
        }

        return $Field;
    }

    /**
     * @return array
     */
    public function getCategories(): array
    {
        if (!$this->categories) {
            try {
                $Real = QUI\ERP\Products\Handler\Products::getProduct($this->getId());
                $this->categories = $Real->getCategories();
            } catch (QUI\Exception) {
            }
        }

        return $this->categories;
    }

    /**
     * @return ?QUI\ERP\Products\Interfaces\CategoryInterface
     */
    public function getCategory(): ?QUI\ERP\Products\Interfaces\CategoryInterface
    {
        if (!$this->Category) {
            try {
                $Real = QUI\ERP\Products\Handler\Products::getProduct($this->getId());
                $this->Category = $Real->getCategory();
            } catch (QUI\Exception) {
            }
        }

        return $this->Category;
    }
}
