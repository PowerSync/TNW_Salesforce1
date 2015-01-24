<?php

/**
 * drop down list
 *
 * Class TNW_Salesforce_Model_Config_Timehour
 */
class TNW_Salesforce_Model_Config_Timehour
{
    /**
     * drop down list method
     *
     * @return mixed
     */
    public function toOptionArray()
    {
        //Get time configuration data - added to help fix ioncube encoding
        return Mage::helper('tnw_salesforce')->syncTimehour();
    }
}