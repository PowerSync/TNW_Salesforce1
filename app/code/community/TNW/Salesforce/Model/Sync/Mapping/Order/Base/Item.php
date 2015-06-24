<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 10.03.15
 * Time: 23:32
 */
class TNW_Salesforce_Model_Sync_Mapping_Order_Base_Item extends TNW_Salesforce_Model_Sync_Mapping_Order_Base
{

    /**
     * @comment list of the allowed mapping types
     * @var array
     */
    protected $_allowedMappingTypes = array(
        'Cart',
        'Product Inventory',
        'Product',
        'Custom'
    );

    /**
     * @comment Gets field mapping from Magento and creates OpportunityLineItem object
     * @param Mage_Sales_Model_Order_Item $entity
     * @param Mage_Catalog_Model_Product $prod
     * @throws Mage_Core_Exception
     */
    protected function _processMapping($entity = NULL, $prod = NULL)
    {

        foreach ($this->getMappingCollection() as $_map) {
            /** @var TNW_Salesforce_Model_Mapping $_map */

            $value = false;

            $mappingType = $_map->getLocalFieldType();
            $attributeCode = $_map->getLocalFieldAttributeCode();

            if (!$this->_mappingTypeAllowed($mappingType)) {
                continue;
            }

            $sf_field = $_map->getSfField();

            $value = $this->_fieldMappingBefore($entity, $mappingType, $attributeCode, $value);

            if (!$this->isBreak()) {

                switch ($mappingType) {
                    case "Cart":
                    case "Item":
                    case "Cart Item":
                        if ($entity) {
                            if ($attributeCode == "total_product_price") {
                                $subtotal = $this->_getNumberFormat(($entity->getPrice() + $entity->getTaxAmount()) * $entity->getQtyOrdered());
                                $value = $this->_getNumberFormat($subtotal - $entity->getDiscountAmount());
                            } else {
                                $value = $entity->getData($attributeCode);

                                // Reformat date fields
                                if ($attributeCode == 'created_at' || $attributeCode == 'updated_at') {
                                    if ($entity->getData($attributeCode)) {
                                        $timestamp = strtotime($entity->getData($attributeCode));
                                        $value = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp($timestamp));
                                    }
                                }
                            }
                        }
                        break;
                    case "Product Inventory":
                        $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($prod);
                        $value = ($stock) ? (int)$stock->getQty() : NULL;
                        break;
                    case "Product":
                        $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
                        $value = ($prod->getAttributeText($attributeCode)) ? $prod->getAttributeText($attributeCode) : $prod->$attr();
                        break;
                    case "Custom":
                        $value = $_map->getCustomValue();
                        break;
                }
            } else {
                $this->setBreak(false);
            }

            $value = $this->_fieldMappingAfter($entity, $mappingType, $attributeCode, $value);

            if ($value) {
                $this->getObj()->$sf_field = trim($value);
            } else {
                Mage::helper('tnw_salesforce')->log($this->_type . ' MAPPING: attribute ' . $sf_field . ' does not have a value in Magento, SKIPPING!');
            }
        }
    }

}
