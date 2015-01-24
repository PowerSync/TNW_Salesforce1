<?php

class TNW_Salesforce_Model_Config_Client_Groups
{

    public function toOptionArray()
    {
        return Mage::helper('tnw_salesforce')->getCustomerGroups();
    }

}
