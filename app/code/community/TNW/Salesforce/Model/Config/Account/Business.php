<?php

class TNW_Salesforce_Model_Config_Account_Business
{

    public function toOptionArray()
    {
        /*
        $_useCache = Mage::app()->useCache('tnw_salesforce');
        $cache = Mage::app()->getCache();

        if ($_useCache && $cache->load("tnw_salesforce_b2b_record_types")) {
            $_data = unserialize($cache->load("tnw_salesforce_b2b_record_types"));
        } else {
            $_data = Mage::helper('tnw_salesforce')->getBusinessAccountRecordIds();
            if ($_useCache) {
                $cache->save(serialize($_data), 'tnw_salesforce_b2b_record_types', array("TNW_SALESFORCE"));
            }
        }
        */
        return Mage::helper('tnw_salesforce')->getBusinessAccountRecordIds();
    }

}
