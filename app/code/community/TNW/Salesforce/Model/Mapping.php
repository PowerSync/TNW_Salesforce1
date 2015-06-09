<?php

/**
 * @method string getLocalField
 * @method string getLocalFieldType
 * @method string getLocalFieldAttributeCode
 * @method string getSfField
 * @method string getAttributeId
 * @method string getBackendType
 * @method string getSfObject
 * @method string getDefaultValue
 *
 * Class TNW_Salesforce_Model_Mapping
 */
class TNW_Salesforce_Model_Mapping extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        parent::_construct();

        $this->_init('tnw_salesforce/mapping');
    }

    protected function _afterLoad()
    {
        parent::_afterLoad();

        list($mappingType, $attributeCode) = explode(" : ", $this->getLocalField());
        $this->setLocalFieldType($mappingType);
        $this->setLocalFieldAttributeCode($attributeCode);

        return $this;
    }

    /**
     * @return bool|null|string
     * @throws Mage_Core_Exception
     */
    public function getProcessedDefaultValue()
    {
        $value = $this->getDefaultValue();
        switch ($this->getDefaultValue()) {
            case '{{url}}':
                return Mage::helper('core/url')->getCurrentUrl();
            case '{{today}}':
                return gmdate('Y-m-d');
            case '{{end of month}}':
                return gmdate('Y-m-d', mktime(0, 0, 0, date('n') + 1, 0, date('Y')));
            case '{{contact id}}':
                /**
                 * @deprecated
                 */
                return null;
            case '{{store view name}}':
                return Mage::app()->getStore()->getName();
            case '{{store group name}}':
                return Mage::app()->getStore()->getGroup()->getName();
            case '{{website name}}':
                return Mage::app()->getWebsite()->getName();
            default:
                return $value;
        }
    }

    /**
     * @param null|int|Mage_Core_Model_Store $store
     * @return bool|null|string
     */
    public function getCustomValue($store = null)
    {
        $store = Mage::app()->getStore($store);
        switch ($this->getLocalFieldAttributeCode()) {
            case 'current_url':
                return Mage::helper('core/url')->getCurrentUrl();
            case 'todays_date':
                return gmdate('Y-m-d');
            case 'todays_timestamp':
                return gmdate(DATE_ATOM);
            case 'end_of_month':
                return gmdate('Y-m-d', mktime(0, 0, 0, date('n') + 1, 0, date('Y')));
            case 'store_view_name':
                return $store->getName();
            case 'store_group_name':
                return is_object($store->getGroup()) ? $store->getGroup()->getName() : null;
            case 'website_name':
                return $store->getWebsite()->getName();
            default:
                return $this->getProcessedDefaultValue();
        }
    }
}