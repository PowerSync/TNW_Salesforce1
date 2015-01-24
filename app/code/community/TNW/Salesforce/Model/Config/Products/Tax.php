<?php

class TNW_Salesforce_Model_Config_Products_Tax
{
    public function toOptionArray()
    {
        /*
        $_useCache = Mage::app()->useCache('tnw_salesforce');
        $cache = Mage::app()->getCache();

        if ($_useCache && $cache->load("tnw_salesforce_opportunity_tax_rate")) {
            $_data = unserialize($cache->load("tnw_salesforce_opportunity_tax_rate"));
        } else {
            $_data = Mage::helper('tnw_salesforce')->taxProductDropdown();
            if ($_useCache) {
                $cache->save(serialize($_data), 'tnw_salesforce_opportunity_tax_rate', array("TNW_SALESFORCE"));
            }
        }
        */
        $_data = Mage::helper('tnw_salesforce')->taxProductDropdown();
        return $_data;
    }

}
