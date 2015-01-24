<?php

class TNW_Salesforce_Model_Config_Client_Type
{

    public function toOptionArray()
    {
        return Mage::helper('tnw_salesforce')->getClientTypes();
    }

}
