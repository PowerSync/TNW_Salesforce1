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
    protected function _prepareValue($_entity)
    {
        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attributeCode) {
            case 'website_ids':
                return $this->convertWebsiteIds($_entity);

            case 'type_id':
                return $this->convertTypeId($_entity);

            case 'attribute_set_id':
                return $this->convertAttributeSetId($_entity);
            case 'status':
                return $this->convertStatus($_entity);
            case 'salesforce_pricebook_id':
                return $this->convertPriceBook($_entity);

        }

        return parent::_prepareValue($_entity);
    }

    /**
     * @param $_entity Mage_Catalog_Model_Product
     * @param $value
     * @return mixed
     */
    protected function _prepareReverseValue($_entity, $value)
    {
        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attributeCode) {
            case 'website_ids':
                return $this->reverseConvertWebsiteIds($value);

            case 'status':
                return $this->reverseConvertStatus($value);

            case 'type_id':
                return $this->reverseConvertTypeId($value);

            case 'attribute_set_id':
                return $this->reverseConvertAttributeSetId($value);

            default:
                return parent::_prepareReverseValue($_entity, $value);
        }
    }

    /**
     * @param $value
     * @return string
     */
    public function reverseConvertStatus($value)
    {
        return ($value === 1 || $value === true) ? Mage_Catalog_Model_Product_Status::STATUS_ENABLED : Mage_Catalog_Model_Product_Status::STATUS_DISABLED;
    }

    /**
     * @param $value
     * @return string
     */
    public function convertStatus($_entity)
    {
        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();

        // Other
        $value = $_entity->getData($attributeCode);
        if (!$value) {
            $method = 'get' . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
            $value = call_user_func(array($_entity, $method));
        }

        return $value == Mage_Catalog_Model_Product_Status::STATUS_ENABLED ? 1 : 0;
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
     * @param $_entity Mage_Catalog_Model_Product
     * @return mixed
     */
    public function convertPriceBook($_entity)
    {
        $_currencyCode = $_entity->getStore()->getCurrentCurrencyCode();
        $priceBookId = null;
        foreach (explode("\n", $_entity->getData('salesforce_pricebook_id')) as $value) {
            if (strpos($value, ':') === false) {
                continue;
            }

            list($_currency, $_priceBook) = explode(':', $value, 2);
            if (empty($_currency)) {
                continue;
            }

            if (empty($_currencyCode) || strcasecmp($_currency, $_currencyCode) === 0) {
                $priceBookId = $_priceBook;
            }
        }

        return $priceBookId;
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