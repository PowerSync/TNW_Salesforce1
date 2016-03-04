<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Sync_Mapping_Customer_Account extends TNW_Salesforce_Model_Sync_Mapping_Customer_Base
{

    protected $_type = 'Account';

    /**
     * @param $_customer
     */
    protected function _addAccountRequiredFields($_customer)
    {
        $this->getObj()->PersonEmail = strtolower($_customer->getEmail());
        $this->getObj()->FirstName = $_customer->getFirstname();
        $this->getObj()->LastName = $_customer->getLastname();

        if (property_exists($this->getObj(), 'Name')) {
            unset($this->getObj()->Name);
        }
    }

    /**
     * @comment Apply mapping rules
     * @param Mage_Customer_Model_Customer $_customer
     */
    protected function _processMapping($_customer = NULL)
    {

        parent::_processMapping($_customer);

        // Get Customer Website Id
        $_websiteId = ($_customer->getData('website_id') != NULL) ? $_customer->getData('website_id') : NULL;

        if (
            Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::BUSINESS_RECORD_TYPE)
            && Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::BUSINESS_RECORD_TYPE) != ''
        ) {
            //$this->getObj()->RecordTypeId = Mage::helper('tnw_salesforce')->getBusinessAccountRecordType();
            $this->getObj()->RecordTypeId = Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::BUSINESS_RECORD_TYPE);
        }

        if ($_customer->getSalesforceAccountId()) {
            $this->getObj()->Id = $_customer->getSalesforceAccountId();
        }

        if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
            $syncParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . "disableMagentoSync__c";
            $this->getObj()->$syncParam = true;
        }

        $_accountName = $_customer->getFirstname() . ' ' . $_customer->getLastname();

        /**
         * @comment find website
         */
        $_website = ($_customer->getWebsiteId()) ? $_customer->getWebsiteId() : NULL;
        if (!$_website && $_customer->getStoreId()) {
            $_website = Mage::getModel('core/store')->load($_customer->getStoreId())->getWebsiteId();
        }
        if ($_website) {
            $sfWebsite = $this->getWebsiteSfIds($_website);
        } else {
            $sfWebsite = 0;
        }

        if (
            !Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::CUSTOMER_PERSON_ACCOUNT)
            || (
                Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::CUSTOMER_PERSON_ACCOUNT)
                && Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::CUSTOMER_FORCE_RECORDTYPE) == TNW_Salesforce_Model_Config_Account_Recordtypes::B2B_ACCOUNT
            )
        ) {
            // This is a potential B2B Account
            if (!property_exists($this->getObj(), 'Name')) {

                if (!empty($_accountName)) {
                    $this->getObj()->Name = $_accountName;
                }
            }
        } else if (Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::PERSON_RECORD_TYPE)) {
            // Configuration is set
            if (
                $this->_cache['contactsLookup']
                && array_key_exists($sfWebsite, $this->_cache['contactsLookup'])
                && array_key_exists($this->_email, $this->_cache['contactsLookup'][$sfWebsite])
                && property_exists($this->getObj(), 'RecordTypeId')
            ) {
                /* Lookup found a match */
                if (
                    property_exists($this->_cache['contactsLookup'][$sfWebsite][$this->_email], 'IsPersonAccount')
                    && $this->_cache['contactsLookup'][$sfWebsite][$this->_email]->IsPersonAccount
                ) {
                    // This is a potential B2C Account
                    $this->getObj()->RecordTypeId = Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::PERSON_RECORD_TYPE);
                    $this->_addAccountRequiredFields($_customer);
                } else {

                    if (!empty($_accountName)) {
                        $this->getObj()->Name = $_accountName;
                    }
                }
            } else if (
                !property_exists($this->getObj(), 'Name')
                || Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::CUSTOMER_FORCE_RECORDTYPE) == TNW_Salesforce_Model_Config_Account_Recordtypes::B2C_ACCOUNT
            ) {
                /* New customer, where account Name is not set */
                // This is a potential B2C Account
                $this->getObj()->RecordTypeId = Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::PERSON_RECORD_TYPE);
                $this->_addAccountRequiredFields($_customer);
            }
        }
        // Overwrite RecordTypeId from existing account
        if (
            $this->_cache['contactsLookup']
            && array_key_exists($sfWebsite, $this->_cache['contactsLookup'])
            && array_key_exists($this->_email, $this->_cache['contactsLookup'][$sfWebsite])
            && property_exists($this->_cache['contactsLookup'][$sfWebsite][$this->_email], 'Account')
            && property_exists($this->_cache['contactsLookup'][$sfWebsite][$this->_email]->Account, 'RecordTypeId')
        ) {
            $this->getObj()->RecordTypeId = $this->_cache['contactsLookup'][$sfWebsite][$this->_email]->Account->RecordTypeId;
        }

        // Assign OwnerId based on Company name match
        $_customerAccountId = $this->_getCustomerAccountId();
        $_accountId = $this->_getCustomerAccountId($this->_email);
        if (empty($_customerAccountId)) {
            if (!property_exists($this->getObj(), 'Id')) {
                if (property_exists($this->getObj(), 'Name') && $this->getObj()->Name) {
                    $_salesforceData = Mage::helper('tnw_salesforce/salesforce_data_account');
                    $_salesforceData->setCompany($this->getObj()->Name);
                    $_companies = $_salesforceData->lookupByCompany();
                    if (!empty($_companies)) {
                        foreach ($_companies as $_account) {
                            // Grab first found account
                            if ($_account->Id) {
                                $this->getObj()->Id = $_account->Id;
                                if ($_account->OwnerId) {
                                    $this->getObj()->OwnerId = $_account->OwnerId;
                                    // Check if user is inactive, then overwrite from configuration
                                    if (!$this->_isUserActive($this->getObj()->OwnerId)) {
                                        $this->getObj()->OwnerId = Mage::helper('tnw_salesforce')->getDefaultOwner();
                                    }
                                    /**
                                     * @comment Save account owner to use it for contact
                                     */
                                    $this->_setCustomerOwnerId($this->getObj()->OwnerId);
                                }
                                break;
                            }
                        }
                    }
                }

                /**
                 * @comment Use improved lookup feature
                 */
                if (
                    isset($this->_cache['accountLookup'][0][$this->_email])
                    && property_exists($this->_cache['accountLookup'][0][$this->_email], 'OwnerId')
                ) {
                    /**
                     * @comment Save account owner to use it for contact
                     */
                    $this->_setCustomerOwnerId($this->_cache['accountLookup'][0][$this->_email]->OwnerId);
                }

                if (!empty($_accountId)) {
                    $this->getObj()->Id = $this->_getCustomerAccountId($this->_email);
                    unset($this->getObj()->RecordTypeId);
                }
            }
        } else {
            if (!empty($_accountId)) {

                $this->getObj()->Id = !empty($_accountId);
                unset($this->getObj()->RecordTypeId);
            }
        }
    }
}