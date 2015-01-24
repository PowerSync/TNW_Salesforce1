<?php

class TNW_Salesforce_Model_Config_Client_Objects
{

    public function getAvailableObjects()
    {
        return array("Lead", "Opportunity", "OpportunityLineItem", "Contact", "Product2");
    }

    public function getAvailableOrderObjects()
    {
        return array("Lead", "Opportunity");
    }

}
