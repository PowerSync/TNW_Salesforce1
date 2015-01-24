<?php

/**
 * Class TNW_Salesforce_Model_Config_Daylist
 */
class TNW_Salesforce_Model_Config_Daylist
{
    /**
     * drop down day list
     *
     * @return mixed
     */
    public function toOptionArray()
    {
        return Mage::helper('tnw_salesforce')->_syncFrequencyDayList();
    }
}