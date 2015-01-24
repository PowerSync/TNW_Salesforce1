<?php

/**
 * Class TNW_Salesforce_Model_Config_Weeklist
 */
class TNW_Salesforce_Model_Config_Weeklist
{
    /**
     * drop down week list
     *
     * @return mixed
     */
    public function toOptionArray()
    {
        return Mage::helper('tnw_salesforce')->_syncFrequencyWeekList();
    }
}