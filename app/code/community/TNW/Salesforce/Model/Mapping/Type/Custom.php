<?php

class TNW_Salesforce_Model_Mapping_Type_Custom extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'Custom';

    /**
     * @param $_entity
     * @return string
     */
    public function getValue($_entity)
    {
        $store = Mage::app()->getStore($_entity);
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

    /**
     * @return bool|null|string
     * @throws Mage_Core_Exception
     */
    public function getProcessedDefaultValue()
    {
        $value = $this->_mapping->getDefaultValue();
        switch ($this->_mapping->getDefaultValue()) {
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
                /**
                 * Is it config path
                 */
                if (substr_count($value, '/') > 1) {
                    $value = Mage::getStoreConfig($value);
                }

                return $value;
        }
    }
}