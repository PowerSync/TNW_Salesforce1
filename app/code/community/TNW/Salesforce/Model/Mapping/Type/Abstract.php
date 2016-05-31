<?php

abstract class TNW_Salesforce_Model_Mapping_Type_Abstract
{
    /**
     * @var TNW_Salesforce_Model_Mapping
     */
    protected $_mapping = null;

    /**
     * @param $_entity Mage_Core_Model_Abstract
     * @return string
     */
    public function getValue($_entity)
    {
        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();

        $method = 'get' . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
        $value = call_user_func(array($_entity, $method));

        $attributeType = $this->_mapping->getBackendType();
        if (empty($attributeType)) {
            $attributeType = $this->_dataType($_entity, $attributeCode);
        }

        switch(true) {
            case is_array($value):
                return implode(' ', $value);

            case in_array($attributeType, array('date', 'datetime', 'timestamp')):
                return gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($value)));

            default:
                return $value;
        }
    }

    /**
     * @param $_entity Mage_Core_Model_Abstract
     * @param $value string
     */
    public function setValue($_entity, $value)
    {
        if (empty($value) && $_entity->isObjectNew()) {
            $value = $this->_mapping->getDefaultValue();
        }

        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();
        $_entity->setData($attributeCode, $value);
    }

    /**
     * @param $_entity Mage_Core_Model_Abstract
     * @param $code
     * @return null
     */
    protected function _dataType($_entity, $code)
    {
        $mainTable = null;

        $resource = $_entity->getResource();
        if ($resource instanceof Mage_Eav_Model_Entity_Abstract) {
            $mainTable = $resource->getEntityTable();
        }

        if ($resource instanceof Mage_Core_Model_Resource_Db_Abstract) {
            $mainTable = $resource->getMainTable();
        }

        if (empty($mainTable)) {
            return null;
        }

        $describe = $resource->getReadConnection()->describeTable($mainTable);
        if (!isset($describe[$code])) {
            return null;
        }

        return $describe[$code]['DATA_TYPE'];
    }

    /**
     * @param $_mapping TNW_Salesforce_Model_Mapping
     * @return $this
     */
    public function setMapping($_mapping)
    {
        $this->_mapping = $_mapping;
        return $this;
    }

    /**
     * @param $item
     * @param int $qty
     * @return float
     */
    protected function _calculateItemPrice($item, $qty = 1)
    {
        $rowTotalField = (Mage::helper('tnw_salesforce')->useTaxFeeProduct())
            ? 'RowTotal' : 'RowTotalInclTax';
        $netTotal = $this->getEntityPrice($item, $rowTotalField);

        if (!Mage::helper('tnw_salesforce')->useDiscountFeeProduct()) {
            $netTotal = ($netTotal - $this->getEntityPrice($item, 'DiscountAmount'));
        }

        return $netTotal / (int)$qty;
    }

    /**
     * @param $value
     * @return mixed
     */
    public function numberFormat($value)
    {
        return Mage::helper('tnw_salesforce/salesforce_data')->numberFormat($value);
    }

    /**
     * @param $_entity
     * @param $priceField
     * @return mixed
     */
    protected function getEntityPrice($_entity, $priceField)
    {
        $origPriceField = $priceField;
        /**
         * use base price if it's selected in config and multicurrency disabled
         */
        if (Mage::helper('tnw_salesforce/config_sales')->useBaseCurrency() && !Mage::helper('tnw_salesforce/config_sales')->isMultiCurrency()) {
            $priceField = 'Base' . $priceField;
        }

        $result = call_user_func(array($_entity, 'get' . $priceField));
        if (!$result) {
            $result = call_user_func(array($_entity, 'get' . $origPriceField));
        }

        return $result;
    }

    /**
     * @param $entity Mage_Core_Model_Abstract
     * @param $code
     * @return bool|Mage_Eav_Model_Entity_Attribute_Abstract
     */
    protected function _getAttribute($entity, $code)
    {
        $resource  = $entity->getResource();
        if (!$resource instanceof Mage_Eav_Model_Entity_Abstract) {
            return false;
        }

        $attribute = $resource->getAttribute($code);
        if (!$attribute instanceof Mage_Eav_Model_Entity_Attribute_Abstract) {
            return false;
        }

        return $attribute;
    }

    /**
     * @param $entity Mage_Core_Model_Abstract
     * @param $attribute Mage_Eav_Model_Entity_Attribute_Abstract
     * @return bool|mixed|string|void
     */
    protected function _convertValueForAttribute($entity, $attribute)
    {
        $value = $entity->getData($attribute->getAttributeCode());
        switch ($attribute->getFrontend()->getConfigField('input'))
        {
            case 'date':
            case 'datetime':
                $value = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp($value));
                break;

            case 'multiselect':
                $value = $attribute->getFrontend()->getOption($value);
                if (is_array($value)) {
                    $value = implode(';', $value);
                }
                break;

            default:
                $value = $attribute->getFrontend()->getValue($entity);
                break;
        }

        return $value;
    }

    /**
     * @param $attribute Mage_Eav_Model_Entity_Attribute_Abstract
     * @param $value
     * @return bool|mixed|string|void
     */
    protected function _reverseConvertValueForAttribute($attribute, $value)
    {
        switch ($attribute->getFrontend()->getConfigField('input'))
        {
            case 'select':
                $source = $attribute->getSource();
                if (!$source) {
                    return null;
                }

                foreach ($source->getAllOptions() as $option) {
                    if (mb_strtolower($option['label'], 'UTF-8') === mb_strtolower($value, 'UTF-8')) {
                        return $option['value'];
                    }
                }

                return null;

            case 'multiselect':
                $value = explode(';', $value);
                $source = $attribute->getSource();
                if (!$source) {
                    return null;
                }

                foreach ($value as &$_value) {
                    foreach ($source->getAllOptions() as $option) {
                        if (mb_strtolower($option['label'], 'UTF-8') === mb_strtolower($_value, 'UTF-8')) {
                            $_value = $option['value'];
                            continue;
                        }
                    }
                }

                return $value;
        }

        return $value;
    }
}