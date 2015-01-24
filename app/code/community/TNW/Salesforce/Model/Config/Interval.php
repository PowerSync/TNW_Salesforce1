<?php

/**
 * drop down list
 *
 * Class TNW_Salesforce_Model_Config_Interval
 */
class TNW_Salesforce_Model_Config_Interval
{
    /**
     * drop down list method
     *
     * @return mixed
     */
    public function toOptionArray()
    {
        //Get time configuration data - added to help fix ioncube encoding
        return Mage::helper('tnw_salesforce')->queueInterval();
    }
}