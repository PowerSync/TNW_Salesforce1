<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 09.03.15
 * Time: 22:22
 */
class TNW_Salesforce_Model_Sync_Mapping_Product_Product extends TNW_Salesforce_Model_Sync_Mapping_Product_Base
{
    /**
     * @var string
     */
    protected $_type = 'Product2';

    /**
     * @comment contains list of the all available product types
     * @var
     */
    protected $_productTypes;

    /**
     * @comment contains list of the all available product Attribute sets
     * @var
     */
    protected $_attribute_sets;

    /**
     * @comment list of the allowed mapping types
     * @var array
     */
    protected $_allowedMappingTypes = array(
        'Product',
        'Product Inventory',
        'Custom'
    );

    /**
     * @param Mage_Catalog_Model_Product $entity
     * @throws Mage_Core_Exception
     */
    protected function _processMapping($entity = NULL)
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
                    case "Product Inventory":
                        $stock = Mage::getModel('cataloginventory/stock_item')->setStoreId(Mage::helper('tnw_salesforce')->getStoreId())->loadByProduct($entity);
                        $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
                        $value = $stock ? $stock->$attr() : null;
                        break;
                    case "Product":
                        $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
                        if ($attributeCode == 'website_ids') {
                            $value = join(',', $entity->$attr());
                        } elseif ($attributeCode == 'type_id') {
                            $value = $entity->$attr();
                            $value = $this->getProductTypes($value);
                       } elseif ($attributeCode == 'product_url') {
                            $value = $entity->getProductUrl();
                        } elseif ($attributeCode == 'attribute_set_id') {
                            $value = $entity->$attr();
                            $value = $this->getAttributeSets($value);
                        } else {
                            $value = ($entity->getAttributeText($attributeCode)) ? $entity->getAttributeText($attributeCode) : $entity->$attr();
                        }
                        break;
                    case "Custom":
                        $value = $_map->getCustomValue();
                }
            } else {
                $this->setBreak(false);
            }

            $value = $this->_fieldMappingAfter($entity, $mappingType, $attributeCode, $value);

            if ($value) {
                $this->getObj()->$sf_field = trim($value);
            } else {
                if (!$this->isFromCLI()) {
                    Mage::helper('tnw_salesforce')->log('PRODUCT MAPPING: attribute ' . $sf_field . ' does not have a value in Magento, SKIPPING!');
                }
            }
        }
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getProductType($id)
    {
        return $this->getProductTypes($id);
    }

    /**
     * @param null $id
     * @return array|null
     */
    public function getProductTypes($id = null)
    {
        if (empty($this->_productTypes)) {
            $this->_productTypes = Mage::getModel('catalog/product_type')->getOptionArray();
        }

        if (!empty($id)) {
            return isset($this->_productTypes[$id]) ? $this->_productTypes[$id] : null;
        }

        return $this->_productTypes;
    }

    /**
     * @param mixed $productTypes
     */
    public function setProductTypes($productTypes)
    {
        $this->_productTypes = $productTypes;
    }

    /**
     * @param null $id
     * @return array|null
     */
    public function getAttributeSet($id = null)
    {
        return $this->getAttributeSets($id);
    }

    /**
     * @param null $id
     * @return array|null
     */
    public function getAttributeSets($id = null)
    {
        if (empty($this->_attribute_sets)) {

            $entityTypeId = Mage::getModel('eav/entity')
                ->setType('catalog_product')
                ->getTypeId();

            $this->_attribute_sets = Mage::getModel('eav/entity_attribute_set')
                ->getCollection()
                ->setEntityTypeFilter($entityTypeId)
                ->toOptionHash();

        }

        if (!empty($id)) {
            return isset($this->_attribute_sets[$id]) ? $this->_attribute_sets[$id] : null;
        }

        return $this->_attribute_sets;
    }

    /**
     * @param mixed $attribute_sets
     */
    public function setAttributeSets($attribute_sets)
    {
        $this->_attribute_sets = $attribute_sets;
    }

}