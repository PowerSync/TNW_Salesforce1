<?php

class TNW_Salesforce_Model_Config_Account_Users
{
    public function toOptionArray()
    {
        return Mage::helper('tnw_salesforce/salesforce_data')->getUsers();
    }
}
