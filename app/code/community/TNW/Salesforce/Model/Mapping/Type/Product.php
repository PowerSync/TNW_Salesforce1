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

        $attribute = $this->_getAttribute($_entity, $attributeCode);
        if ($attribute) {
            if($_entity->hasData($attributeCode)) {
                return $this->_convertValueForAttribute($_entity, $attribute);
            }

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice(sprintf('Attribute product "%s" is missing. Product sku: "%s"', $attributeCode, $_entity->getSku()));
        }

        return parent::getValue($_entity);
    }

    /**
     * @param Mage_Catalog_Model_Product $entity
     * @param string $value
     * @return string
     */
    public function setValue($entity, $value)
    {
        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attributeCode) {
            case 'website_ids':
                $value = $this->reverseConvertWebsiteIds($value);
                break;

            case 'status':
                $value = $this->reverseConvertStatus($value);
                break;

            case 'type_id':
                $value = $this->reverseConvertTypeId($value);
                break;

            case 'attribute_set_id':
                $value = $this->reverseConvertAttributeSetId($value);
                break;
        }

        $attribute = $this->_getAttribute($entity, $attributeCode);
        if ($attribute) {
            $value = $this->_reverseConvertValueForAttribute($attribute, $value);
        }

        parent::setValue($entity, $value);
    }

    /**
     * @param $value
     * @return string
     */
    public function reverseConvertStatus($value)
    {
        return ($value === 1 || $value === true) ? 'Enabled' : 'Disabled';
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
     * @param $value
     * @return array
     */
    public function reverseConvertWebsiteIds($value)
    {
        return explode(',', $value);
    }

    /**
     * @param $_entity Mage_Catalog_Model_Product
     * @return string
     */
    public function convertTypeId($_entity)
    {
        $value = $_entity->getTypeId();
        if (empty($value)) {
            return null;
        }

        $productTypes = $this->getProductTypes();
        if (!isset($productTypes[$value])) {
            return null;
        }

        return $productTypes[$value];
    }

    /**
     * @param $value string
     * @return mixed|string
     */
    public function reverseConvertTypeId($value)
    {
        $productTypes = $this->getProductTypes();
        $result = array_search($value, $productTypes);

        if (false === $result) {
            // Temporary solution
            $result = array_search($value, Mage::getModel('catalog/product_type')->getOptionArray());
            if (false !== $result) {
                return $result;
            }

            $result = $value;
        }

        return $result;
    }

    /**
     * @return array|null
     */
    protected function getProductTypes()
    {
        if (empty($this->_productTypes)) {
            $this->_productTypes = array_map(function($type) {
                return $type['label'];
            }, Mage::getConfig()->getNode('global/catalog/product/type')->asArray());
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
        return Mage::getSingleton('catalog/config')
            ->getAttributeSetName(Mage_Catalog_Model_Product::ENTITY, $value);
    }

    /**
     * @param $value string
     * @return string
     */
    public function reverseConvertAttributeSetId($value)
    {
        $value = Mage::getSingleton('catalog/config')
            ->getAttributeSetId(Mage_Catalog_Model_Product::ENTITY, $value);

        if (!$value) {
            $value = Mage::getResourceSingleton('catalog/product')
                ->getEntityType()
                ->getDefaultAttributeSetId();
        }

        return $value;
    }
}