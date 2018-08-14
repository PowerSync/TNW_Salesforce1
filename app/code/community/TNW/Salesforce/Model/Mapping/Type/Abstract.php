<?php

abstract class TNW_Salesforce_Model_Mapping_Type_Abstract
{
    /**
     * @var TNW_Salesforce_Model_Mapping
     */
    protected $_mapping = null;

    /**
     * @param $_entity Mage_Core_Model_Abstract
     * @param $additional mixed
     * @return string
     */
    public function getValue($_entity, $additional = null)
    {
        $value = $this->_prepareValue($_entity);

        $describe = Mage::helper('tnw_salesforce/salesforce_data')
            ->describeTable($this->_mapping->getSfObject());

        /**
         * try to find SF field
         */
        $appropriatedField = false;
        if (!empty($describe->fields)) {
            foreach ($describe->fields as $field) {
                if (strtolower($field->name) == strtolower($this->_mapping->getSfField())) {
                    $appropriatedField = $field;
                    break;
                }
            }
        }

        /**
         * apply field limits
         */
        if ($appropriatedField) {
            try {

                if (!$appropriatedField->createable && ($additional instanceof stdClass) && empty($additional->Id)) {
                   throw new Exception($this->_mapping->getSfField() . ' Salesforce field is not creatable, value sync skipped');
                }

                if (!$appropriatedField->updateable && ($additional instanceof stdClass) && !empty($additional->Id)) {
                    throw new Exception($this->_mapping->getSfField() . ' Salesforce field is not updateable, value sync skipped');
                }

                if (
                    is_string($value)
                    && $appropriatedField->length
                    && $appropriatedField->length < strlen($value)
                ) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Truncating a long value for an ' . $this->_mapping->getSfObject() . ': ' . $this->_mapping->getSfField());
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Limit is ' . $appropriatedField->length . ' value length is ' . strlen($value));
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Initial value: ' . $value);
                    $limit = $appropriatedField->length;
                    $value = mb_substr($value, 0, $limit - 3) . '...';
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Truncated value: ' . $value);
                }

                $value = $this->_prepareDefaultValue($value);

                //For Attribute
                $attributeCode = $this->_mapping->getLocalFieldAttributeCode();
                $attribute = $this->_getAttribute($_entity, $attributeCode);
                if (is_null($value) && $attribute && $attribute->getFrontend()->getConfigField('input') == 'multiselect') {
                    $value = ' ';
                }
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($e->getMessage());
                $value = null;
            }
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice("Field \"{$this->_mapping->getSfObject()}::{$this->_mapping->getSfField()}\" not found in SF! Skipped field.");

            $value = null;
        }


        return $value;
    }

    /**
     * @param $_entity Mage_Core_Model_Abstract
     * @return mixed
     */
    protected function _prepareValue($_entity)
    {
        //For Attribute
        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();
        $attribute = $this->_getAttribute($_entity, $attributeCode);
        if ($attribute && $_entity->hasData($attributeCode)) {
            return $this->_convertValueForAttribute($_entity, $attribute);
        }

        // Other
        $value = $_entity->getData($attributeCode);
        if (!$value) {
            $method = 'get' . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
            $value = call_user_func(array($_entity, $method));
        }
        if (is_object($value)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Value of the ' . $attributeCode . ' is object, cannot be used for sync process.');
            $value = null;
        }

        $attributeType = $this->_mapping->getBackendType();
        if (empty($attributeType)) {
            $attributeType = $this->_dataType($_entity, $attributeCode);
        }

        switch (true) {
            case is_array($value):
                return implode(' ', $value);

            case in_array($attributeType, array('date', 'datetime', 'timestamp')):
                if (empty($value)) {
                    return null;
                }

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

        if (is_null($value) || (is_string($value) && '' === trim($value))) {
            return;
        }

        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();
        $_entity->setData($attributeCode, $value);
    }

    /**
     * @param $value
     * @return string
     */
    protected function _prepareDefaultValue($value)
    {
        if (is_null($value) || (is_string($value) && '' === trim($value))) {
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
        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();
        $attribute = $this->_getAttribute($_entity, $attributeCode);
        if ($attribute) {
            $value = $this->_reverseConvertValueForAttribute($attribute, $value);
        }

        // Other
        $attributeType = $this->_mapping->getBackendType();
        if (empty($attributeType)) {
            $attributeType = $this->_dataType($_entity, $attributeCode);
        }

        switch (true) {
            case in_array($attributeType, array('date', 'datetime', 'timestamp')):
                if (empty($value)) {
                    $value = null;
                    break;
                }

                $value = $this->_reversePrepareDateTime($value)->format('Y-m-d H:i:s');
                break;
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
     * @param bool $includeTax
     * @param bool $includeDiscount
     * @param int $qty
     * @return float
     */
    protected function _calculateItemPrice($item, $qty = 1, $includeTax = true, $includeDiscount = true)
    {
        $rowTotal = $this->getEntityPrice($item, 'RowTotal');

        if ($includeTax) {
            $rowTotal += $this->getEntityPrice($item, 'TaxAmount');
            $rowTotal += $this->getEntityPrice($item, 'HiddenTaxAmount');
        }

        if ($includeDiscount) {
            $rowTotal -= $this->getEntityPrice($item, 'DiscountAmount');
        }

        return $rowTotal / (int)$qty;
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

        return floatval($result);
    }

    /**
     * @param $entity Mage_Core_Model_Abstract
     * @param $code
     * @return bool|Mage_Eav_Model_Entity_Attribute_Abstract
     */
    protected function _getAttribute($entity, $code)
    {
        $resource = $entity->getResource();
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
        switch ($attribute->getFrontend()->getConfigField('input')) {
            case 'date':
            case 'datetime':
                if (empty($value)) {
                    $value = null;
                    break;
                }

                $value = $this->_prepareDateTime($value)->format('c');
                break;

            case 'multiselect':
                $value = $attribute->getFrontend()->getOption($value);
                switch (true) {
                    case (false === $value):
                        $value = null;
                        break 2;

                    case is_array($value):
                        $value = implode(';', $value);
                        break 2;
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
        switch ($attribute->getFrontend()->getConfigField('input')) {
            case 'date':
            case 'datetime':
                if (empty($value)) {
                    $value = null;
                    break;
                }

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
        $currentTimezone = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);

        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();
        $timezone = !in_array($attributeCode, array('created_at', 'updated_at'))
            ? $currentTimezone
            : 'UTC';

        $dateTime = new DateTime(date('Y-m-d H:i:s', strtotime($date)), new DateTimeZone($timezone));
        return $dateTime->setTimezone(new DateTimeZone($currentTimezone));
    }

    /**
     * @param string $date
     * @return DateTime
     */
    protected function _reversePrepareDateTime($date)
    {
        $currentTimezone = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
        $timezoneForce = !preg_match('/\d{4}-\d{2}-\d{2}T/i', $date) && !preg_match('/\d{4}-\d{2}-\d{2}/i', $date)? new DateTimeZone($currentTimezone) : null;

        $dateTime = new DateTime($date, $timezoneForce);

        $timezoneObj = new DateTimeZone($currentTimezone);
        if (!is_null($timezoneForce)) {
            $dateTime->setTimezone($timezoneObj);
        } else {

            $dateObj = new DateTime("now");
            $timeOffset = $timezoneObj->getOffset($dateObj);

            /**
             * set 12 pm if no time information here
             */
            $dateTime->setTime(0, 0, 0);

            /**
             * reduce the time to compensate Time zone offset
             */
            $timeOffsetInterval = new DateInterval('PT'.abs($timeOffset).'S');
            if ($timeOffset > 0) {
                $dateTime->sub($timeOffsetInterval);
            } else {
                $dateTime->add($timeOffsetInterval);
            }
        }

        return $dateTime;
    }

    /**
     * Read from cache or pull from Salesforce Active users
     * Accept $_sfUserId parameter and check if its in the array of active users
     * @param null $_sfUserId
     * @return bool
     */
    protected function _isUserActive($_sfUserId = NULL)
    {
        return Mage::helper('tnw_salesforce/salesforce_data_user')->isUserActive($_sfUserId);
    }

    /**
     * @param $className
     * @return null|TNW_Salesforce_Helper_Salesforce_Customer
     */
    public function getHelperInstance($classAlias)
    {
        $className = Mage::getConfig()->getHelperClassName($classAlias);
        $currentHelper = null;
        foreach (TNW_Salesforce_Helper_Salesforce_Abstract::$usedHelpers as $helper) {

            if ($helper instanceof $className) {
                $currentHelper = $helper;
                break;
            }
        }

        return $currentHelper;
    }

    /**
     * Check array of owners and return first active user
     * @param $availableOwners
     * @return null
     */
    public function getFirstAvailableOwner($availableOwners)
    {

        $result = null;
        foreach ($availableOwners as $owner) {
            if (!empty($owner) && $this->_isUserActive($owner)) {
                $result = $owner;
                break;
            }
        }

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf('Find available owner. Owners list: %s. Chosen: %s', implode(', ', $availableOwners), $result));

        return $result;
    }
}