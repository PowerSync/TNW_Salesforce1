<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Config_Customer extends TNW_Salesforce_Helper_Config
{
    const NEW_CUSTOMER = 'salesforce_customer/sync/new_customer';
    const CONFIG_CONTACT_ASIGNEE = 'salesforce_customer/sync/contact_asignee';
    const CONFIG_MERGE_DUPLICATES = 'salesforce_customer/sync/merge_duplicates';
    const CONFIG_ACCOUNT_SYNC_CUSTOMER = 'salesforce_customer/sync/single_account_sync_customer';
    const CONFIG_ACCOUNT_SELECT = 'salesforce_customer/sync/single_account_select';

    // Create new customers from Salesforce
    public function allowSalesforceToCreate()
    {
        return $this->getStoreConfig(self::NEW_CUSTOMER);
    }

    /**
     * @return null|string
     */
    public function getContactAsignee()
    {
        return $this->getStoreConfig(self::CONFIG_CONTACT_ASIGNEE);
    }

    /**
     * @comment return true if owner from config should be used
     * @return bool
     */
    public function useDefaultOwner()
    {
        return $this->getStoreConfig(self::CONFIG_CONTACT_ASIGNEE) == TNW_Salesforce_Model_Config_Contact_Asignee::CONTACT_ASIGNEE_DEFAULT;
    }

    /**
     * @return mixed
     */
    public function mergeDuplicates()
    {
        return $this->getStoreConfig(self::CONFIG_MERGE_DUPLICATES);
    }

    /**
     * @return bool
     */
    public function useAccountSyncCustomer()
    {
        return $this->getStoreConfig(self::CONFIG_ACCOUNT_SYNC_CUSTOMER)
            && $this->getAccountSelect() !== 'null';
    }

    /**
     * @return mixed|null|string
     */
    public function getAccountSelect()
    {
        return $this->getStoreConfig(self::CONFIG_ACCOUNT_SELECT);
    }
}