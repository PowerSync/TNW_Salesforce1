<?php

class TNW_Salesforce_Model_Config_Account_Users
{
    public function toOptionArray()
    {
        $_useCache = Mage::app()->useCache('tnw_salesforce');
        $cache = Mage::app()->getCache();

        if ($_useCache && $cache->load("tnw_salesforce_users")) {
            $_data = unserialize($cache->load("tnw_salesforce_users"));
        } else {
            $_data = Mage::helper('tnw_salesforce/salesforce_data')->getUsers();
            if ($_useCache) {
                $cache->save(serialize($_data), 'tnw_salesforce_users', array("TNW_SALESFORCE"), 60 * 60 * 24);
            }
        }

        return $_data;
    }
}
