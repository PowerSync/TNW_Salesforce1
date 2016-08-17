<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Config_Customer extends TNW_Salesforce_Helper_Config
{
    const NEW_CUSTOMER = 'salesforce_customer/sync/new_customer';
    const CONFIG_CONTACT_ASIGNEE = 'salesforce_customer/contact/contact_asignee';
    const CONFIG_MERGE_ACCOUNT_DUPLICATES = 'salesforce_customer/account/merge_duplicates';
    const CONFIG_MERGE_CONTACT_DUPLICATES = 'salesforce_customer/contact/merge_duplicates';
    const CONFIG_MERGE_LEAD_DUPLICATES = 'salesforce_customer/lead_config/merge_duplicates';
    const CONFIG_ACCOUNT_SYNC_CUSTOMER = 'salesforce_customer/account/single_account_sync_customer';
    const CONFIG_ACCOUNT_SELECT = 'salesforce_customer/account/single_account_select';
    const CONFIG_ACCOUNT_PICKLIST = 'salesforce_customer/sync/use_address_picklist';
    const CONFIG_OPPORTUNITY_VIEW = 'salesforce_customer/customer_view/opportunity_display';
    const CONFIG_OPPORTUNITY_FILTER = 'salesforce_customer/customer_view/opportunity_filter';
    const CONFIG_LEAD_EMAIL_NOTIFICATION = 'salesforce_customer/lead_config/email_notification';

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
     * @return bool
     */
    public function mergeAccountDuplicates()
    {
        return $this->getStoreConfig(self::CONFIG_MERGE_ACCOUNT_DUPLICATES);
    }

    /**
     * @return bool
     */
    public function mergeContactDuplicates()
    {
        return $this->getStoreConfig(self::CONFIG_MERGE_CONTACT_DUPLICATES);
    }

    /**
     * @return bool
     */
    public function mergeLeadDuplicates()
    {
        return $this->getStoreConfig(self::CONFIG_MERGE_LEAD_DUPLICATES);
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

    /**
     * @return mixed|null|string
     */
    public function useAddressPicklist()
    {
        return $this->getStoreConfig(self::CONFIG_ACCOUNT_PICKLIST);
    }

    /**
     * @return bool
     */
    public function isOpportunityView()
    {
        return (bool)$this->getStoreConfig(self::CONFIG_OPPORTUNITY_VIEW);
    }

    /**
     * @return string
     */
    public function getOpportunityFilterType()
    {
        return $this->getStoreConfig(self::CONFIG_OPPORTUNITY_FILTER);
    }

    /**
     * @return array
     */
    public function getAccordionConfig()
    {
        return array(
            'title'       => $this->__('Customer Opportunities'),
            'ajax'        => true,
            'content_url' => Mage::getSingleton('core/url')->getUrl('*/*/opportunities', array('_current' => true)),
        );
    }

    /**
     * @return bool
     */
    public function isLeadEmailNotification()
    {
        return (bool)$this->getStoreConfig(self::CONFIG_LEAD_EMAIL_NOTIFICATION);
    }

    public function getSyncButtonData()
    {
        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::registry('current_customer');
        $url      = Mage::getModel('adminhtml/url')->getUrl('*/salesforcesync_customersync/sync', array('customer_id' => $customer->getId()));

        return array(
            'label'   => Mage::helper('tnw_salesforce')->__('Synchronize w/ Salesforce'),
            'onclick' => "setLocation('$url')",
        );
    }
}