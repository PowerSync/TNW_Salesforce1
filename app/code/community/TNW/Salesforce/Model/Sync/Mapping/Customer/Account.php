<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 10.03.15
 * Time: 22:21
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
        $this->getObj()->FirstName = strtolower($_customer->getFirstname());
        $this->getObj()->LastName = strtolower($_customer->getLastname());
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

        if (
            Mage::helper('tnw_salesforce')->getBusinessAccountRecordType()
            && Mage::helper('tnw_salesforce')->getBusinessAccountRecordType() != ''
        ) {
            $this->getObj()->RecordTypeId = Mage::helper('tnw_salesforce')->getBusinessAccountRecordType();
        }

        if ($_customer->getSalesforceAccountId()) {
            $this->getObj()->Id = $_customer->getSalesforceAccountId();
        }
        $_accountName = $_customer->getFirstname() . ' ' . $_customer->getLastname();
        $store = ($_customer->getStoreId() !== NULL) ? Mage::getModel('core/store')->load($_customer->getStoreId()) : NULL;

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
            !Mage::helper('tnw_salesforce')->usePersonAccount()
            || (Mage::helper('tnw_salesforce')->usePersonAccount() && Mage::helper('tnw_salesforce')->isCustomerSingleRecordType() == TNW_Salesforce_Model_Config_Account_Recordtypes::B2B_ACCOUNT)
        ) {
            // This is a potential B2B Account
            if (!property_exists($this->getObj(), 'Name')) {
                if (
                    !Mage::helper('tnw_salesforce')->canRenameAccount()
                    && $this->_cache['contactsLookup']
                    && array_key_exists($sfWebsite, $this->_cache['contactsLookup'])
                    && array_key_exists($_customer->getEmail(), $this->_cache['contactsLookup'][$sfWebsite])
                    && property_exists($this->_cache['contactsLookup'][$sfWebsite][$_customer->getEmail()], 'AccountName')
                    && $this->_cache['contactsLookup'][$sfWebsite][$_customer->getEmail()]->AccountName
                ) {
                    $_accountName = $this->_cache['contactsLookup'][$sfWebsite][$_customer->getEmail()]->AccountName;
                }
                if (!empty($_accountName)) {
                    $this->getObj()->Name = $_accountName;
                }
            }
        } else if (Mage::helper('tnw_salesforce')->getPersonAccountRecordType()) {
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
                    $this->getObj()->RecordTypeId = Mage::helper('tnw_salesforce')->getPersonAccountRecordType();
                    $this->_addAccountRequiredFields($_customer);
                } else {
                    // This is a potential B2B Account
                    $_accountName = (
                        property_exists($this->_cache['contactsLookup'][$sfWebsite][$this->_email], 'AccountName')
                        && $this->_cache['contactsLookup'][$sfWebsite][$this->_email]->AccountName
                        && !Mage::helper('tnw_salesforce')->canRenameAccount()
                    ) ? $this->_cache['contactsLookup'][$sfWebsite][$this->_email]->AccountName : $_accountName;

                    if (!empty($_accountName)) {
                        $this->getObj()->Name = $_accountName;
                    }
                }
            } else if (!property_exists($this->getObj(), 'Name')) {
                /* New customer, where account Name is not set */
                // This is a potential B2C Account
                $this->getObj()->RecordTypeId = Mage::helper('tnw_salesforce')->getPersonAccountRecordType();
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
                                    $this->_setCustomerOwnerId($this->getObj()->OwnerId);
                                }
                                break;
                            }
                        }
                    }
                }

                if (!empty($_accountId)) {
                    $this->getObj()->Id = $this->_getCustomerAccountId($this->_email);
                    if (!Mage::helper('tnw_salesforce')->canRenameAccount()) {
                        unset($this->getObj()->Name);
                    }
                    unset($this->getObj()->RecordTypeId);
                }
            }
        } else {
            if (!empty($_accountId)) {

                $this->getObj()->Id = !empty($_accountId);
                if (!Mage::helper('tnw_salesforce')->canRenameAccount()) {
                    unset($this->getObj()->Name);
                }
                unset($this->getObj()->RecordTypeId);
            }
        }
    }
}