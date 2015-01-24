<?php

/**
 * order sync status drop down list helper
 *
 * Class TNW_Salesforce_Model_Config_Products_Queuesyncinterval
 */
class TNW_Salesforce_Model_Config_Products_Queuesyncinterval
{
    /**
     * return select list of queue sync interval
     *
     * @return mixed
     */
    public function toOptionArray()
    {
        $_useCache = Mage::app()->useCache('tnw_salesforce');
        $cache = Mage::app()->getCache();

        if ($_useCache && $cache->load("tnw_salesforce_queuesyncinterval_status_list")) {
            $_data = unserialize($cache->load("tnw_salesforce_queuesyncinterval_status_list"));
        } else {
            $_data = Mage::helper('tnw_salesforce')->queueSyncIntervalDropdown();
            if ($_useCache) {
                $cache->save(serialize($_data), 'tnw_salesforce_queuesyncinterval_status_list', array("TNW_SALESFORCE"));
            }
        }

        return $_data;
    }
}