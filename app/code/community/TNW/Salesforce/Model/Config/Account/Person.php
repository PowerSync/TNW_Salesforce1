<?php

class TNW_Salesforce_Model_Config_Account_Person
{

    public function toOptionArray()
    {
        return Mage::helper('tnw_salesforce')->getPersonAccountRecordIds();
    }

}
