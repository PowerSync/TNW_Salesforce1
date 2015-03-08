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
        return Mage::helper('tnw_salesforce')->queueSyncIntervalDropdown();
    }
}