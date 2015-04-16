<?php

class TNW_Salesforce_Model_Config_Account_Business
{

    public function toOptionArray()
    {
        return Mage::helper('tnw_salesforce')->getBusinessAccountRecordIds();
    }

}
