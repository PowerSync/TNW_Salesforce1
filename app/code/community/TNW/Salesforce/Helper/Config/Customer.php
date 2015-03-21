<?php

class TNW_Salesforce_Helper_Config_Customer extends TNW_Salesforce_Helper_Config
{
    const NEW_CUSTOMER = 'salesforce_customer/sync/new_customer';

    // Create new customers from Salesforce
    public function allowSalesforceToCreate()
    {
        return $this->getStroreConfig(self::NEW_CUSTOMER);
    }
}