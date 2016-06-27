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
        $value = $this->_prepareValue($_entity);
        $value = $this->_prepareDefaultValue($value);

        return $value;
    }

    /**
     * @param $_entity Mage_Core_Model_Abstract
     * @return mixed
     */
    protected function _prepareValue($_entity)
    {
        //For Attribute
        $attributeCode  = $this->_mapping->getLocalFieldAttributeCode();
        $attribute      = $this->_getAttribute($_entity, $attributeCode);
        if ($attribute && $_entity->hasData($attributeCode)) {
            return $this->_convertValueForAttribute($_entity, $attribute);
        }

        // Other
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
                return $this->_prepareDateTime($value)->format('c');

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
        $value = $this->_prepareDefaultValue($value);
        $value = $this->_prepareReverseValue($_entity, $value);

        $attributeCode  = $this->_mapping->getLocalFieldAttributeCode();
        $_entity->setData($attributeCode, $value);
    }

    /**
     * @param $value
     * @return string
     */
    protected function _prepareDefaultValue($value)
    {
        if (empty($value)) {
            $value = $this->_mapping->getDefaultValue();
        }

        return $value;
    }

    /**
     * @param $_entity Mage_Core_Model_Abstract
     * @param $value
     * @return mixed
     */
    protected function _prepareReverseValue($_entity, $value)
    {
        // For Attribute
        $attributeCode  = $this->_mapping->getLocalFieldAttributeCode();
        $attribute      = $this->_getAttribute($_entity, $attributeCode);
        if ($attribute) {
            $value = $this->_reverseConvertValueForAttribute($attribute, $value);
        }

        // Other
        $attributeType = $this->_mapping->getBackendType();
        if (empty($attributeType)) {
            $attributeType = $this->_dataType($_entity, $attributeCode);
        }

        switch(true) {
            case in_array($attributeType, array('date', 'datetime', 'timestamp')):
                $value = $this->_reversePrepareDateTime($value)->format('Y-m-d H:i:s');
        }

        return $value;
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
                $value = $this->_prepareDateTime($value)->format('c');
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
            case 'date':
            case 'datetime':
                $value = $this->_reversePrepareDateTime($value)->format('Y-m-d H:i:s');
                break;

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

    /**
     * @param string $date
     * @return DateTime
     */
    protected function _prepareDateTime($date)
    {
        $attributeCode  = $this->_mapping->getLocalFieldAttributeCode();
        $timezone = !in_array($attributeCode, array('created_at', 'updated_at'))
            ? Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE)
            : 'UTC';

        return new \DateTime(date('Y-m-d H:i:s', strtotime($date)), new \DateTimeZone($timezone));
    }

    /**
     * @param string $date
     * @return DateTime
     */
    protected function _reversePrepareDateTime($date)
    {
        $attributeCode  = $this->_mapping->getLocalFieldAttributeCode();
        $timezone = !in_array($attributeCode, array('created_at', 'updated_at'))
            ? Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE)
            : 'UTC';

        $timezone       = new \DateTimeZone($timezone);
        $timezoneForce  = !preg_match('/\d{4}-\d{2}-\d{2}T/i', $date) ? $timezone : null;

        $dateTime = new \DateTime($date, $timezoneForce);
        $dateTime->setTimezone($timezone);

        return $dateTime;
    }
}