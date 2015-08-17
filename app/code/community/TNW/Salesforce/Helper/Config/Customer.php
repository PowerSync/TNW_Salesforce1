<?php

class TNW_Salesforce_Helper_Config_Customer extends TNW_Salesforce_Helper_Config
{
    const NEW_CUSTOMER = 'salesforce_customer/sync/new_customer';
    const CONFIG_CONTACT_ASIGNEE = 'salesforce_customer/sync/contact_asignee';
    const CONFIG_MERGE_DUPLICATES = 'salesforce_customer/sync/merge_duplicates';

    // Create new customers from Salesforce
    public function allowSalesforceToCreate()
    {
        return $this->getStroreConfig(self::NEW_CUSTOMER);
    }

    /**
     * @return null|string
     */
    public function getContactAsignee()
    {
        return $this->getStroreConfig(self::CONFIG_CONTACT_ASIGNEE);
    }

    /**
     * @comment return true if owner from config should be used
     * @return bool
     */
    public function useDefaultOwner()
    {
        return $this->getStroreConfig(self::CONFIG_CONTACT_ASIGNEE) == TNW_Salesforce_Model_Config_Contact_Asignee::CONTACT_ASIGNEE_DEFAULT;
    }

    /**
     * @return mixed
     */
    public function mergeDuplicates()
    {
        return $this->getStroreConfig(self::CONFIG_MERGE_DUPLICATES);
    }
}