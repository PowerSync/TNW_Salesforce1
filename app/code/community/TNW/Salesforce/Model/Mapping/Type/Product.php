<?php

/**
 * Class TNW_Salesforce_Model_Mapping_Type_Product
 */
class TNW_Salesforce_Model_Mapping_Type_Product extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'Product';

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
     * @param $_entity Mage_Catalog_Model_Product
     * @return string
     */
    public function getValue($_entity)
    {
        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attributeCode) {
            case 'website_ids':
                return $this->convertWebsiteIds($_entity);

            case 'type_id':
                return $this->convertTypeId($_entity);

            case 'attribute_set_id':
                return $this->convertAttributeSetId($_entity);
        }

        /** @var mage_catalog_model_resource_product $_resource */
        $_resource = Mage::getResourceSingleton('catalog/product');
        $attribute = $_resource->getAttribute($attributeCode);
        if ($attribute)
        {
            if(!$_entity->hasData($attributeCode)) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveNotice(sprintf('Attribute product "%s" is missing. Product sku: "%s"', $attributeCode, $_entity->getSku()));
            }

            $_value = $_entity->getData($attributeCode);
            switch($attribute->getFrontend()->getInputType())
            {
                case 'select':
                case 'multiselect':
                    return $attribute->getSource()->getOptionText($_value);

                default:
                    return $_value;
            }
        }

        return parent::getValue($_entity);
    }

    /**
     * @param $_entity Mage_Catalog_Model_Product
     * @return string
     */
    public function convertNumber($_entity)
    {
        return $_entity->getId();
    }

    /**
     * @param $_entity Mage_Catalog_Model_Product
     * @return string
     */
    public function convertWebsiteIds($_entity)
    {
        return join(',', $_entity->getWebsiteIds());
    }

    /**
     * @param $_entity Mage_Catalog_Model_Product
     * @return string
     */
    public function convertTypeId($_entity)
    {
        $value = $_entity->getTypeId();
        return $this->getProductTypes($value);
    }

    /**
     * @param null $id
     * @return array|null
     */
    protected function getProductTypes($id = null)
    {
        if (empty($this->_productTypes)) {
            $this->_productTypes = array_map(function($type) {
                return $type['label'];
            }, Mage::getConfig()->getNode('global/catalog/product/type')->asArray());
        }

        if (!empty($id)) {
            return isset($this->_productTypes[$id]) ? $this->_productTypes[$id] : null;
        }

        return $this->_productTypes;
    }

    /**
     * @param $_entity Mage_Catalog_Model_Product
     * @return string
     */
    public function convertAttributeSetId($_entity)
    {
        $value = $_entity->getAttributeSetId();
        return $this->getAttributeSets($value);
    }

    /**
     * @param null $id
     * @return array|null
     */
    protected function getAttributeSets($id = null)
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
}