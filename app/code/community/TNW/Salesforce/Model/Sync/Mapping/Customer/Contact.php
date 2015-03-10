<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 10.03.15
 * Time: 22:21
 */
class TNW_Salesforce_Model_Sync_Mapping_Customer_Contact extends TNW_Salesforce_Model_Sync_Mapping_Customer_Base
{

    protected $_type = 'Contact';

    /**
     * @comment Apply mapping rules
     * @param Mage_Customer_Model_Customer $_customer
     */
    protected function _processMapping($_customer = NULL)
    {
        parent::_processMapping($_customer);

        //Use data in Salesforce if Magento data is blank for the First and Last name
        if (!property_exists($this->getObj(), 'FirstName') || !$this->getObj()->FirstName) {
            // Check if lookup has the data
            if (
                array_key_exists($this->_email, $this->_cache['contactsLookup'])
                && property_exists($this->_cache['contactsLookup'][$this->_email], 'FirstName')
                && $this->_cache['contactsLookup'][$this->_email]->FirstName
            ) {
                $this->getObj()->FirstName = $this->_cache['contactsLookup'][$this->_email]->FirstName;
                $_customer->setFirstname($this->getObj()->FirstName);
                $this->_cache['toSaveInMagento'][$this->_websiteId][$this->_email]->FirstName = $this->getObj()->FirstName;
            }
        }
        if (!property_exists($this->getObj(), 'LastName') || !$this->getObj()->LastName) {
            // Check if lookup has the data
            if (
                array_key_exists($this->_email, $this->_cache['contactsLookup'])
                && property_exists($this->_cache['contactsLookup'][$this->_email], 'LastName')
                && $this->_cache['contactsLookup'][$this->_email]->LastName
            ) {
                $this->getObj()->LastName = $this->_cache['contactsLookup'][$this->_email]->LastName;
                $_customer->setLastname($this->getObj()->LastName);
                $this->_cache['toSaveInMagento'][$this->_websiteId][$this->_email]->LastName = $this->getObj()->LastName;
            }
        }
        //Account
        $this->getObj()->AccountId = $_customer->getSalesforceAccountId();

        if (!$this->getObj()->AccountId && !empty($this->_getCustomerAccountId($this->_email)) ) {
            $this->getObj()->AccountId = $this->_getCustomerAccountId($this->_email);
        } elseif (!$this->getObj()->AccountId && !is_array($this->_getCustomerAccountId()) && $this->_getCustomerAccountId()) {
            $this->getObj()->AccountId = $this->_getCustomerAccountId();
        }

        if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
            if (
                array_key_exists($this->_websiteId, $this->_cache['toSaveInMagento'])
                && array_key_exists($this->_email, $this->_cache['toSaveInMagento'])
                && is_object($this->_cache['toSaveInMagento'][$this->_email][$this->_websiteId])
                && property_exists($this->_cache['toSaveInMagento'][$this->_websiteId][$this->_email], 'IsPersonAccount')
                && $this->_cache['toSaveInMagento'][$this->_websiteId][$this->_email]->IsPersonAccount
            ) {
                unset($this->getObj()->AccountId);
            }
            if (
                !Mage::helper('tnw_salesforce')->isCustomerAsLead()
                && array_key_exists($_customer->getId(), $this->_cache['notFoundCustomers'])
            ) {
                unset($this->getObj()->AccountId);
            }
            if (Mage::helper('tnw_salesforce')->isCustomerSingleRecordType() == 2) {
                // B2C only
                unset($this->getObj()->AccountId);
            }
        }
        // Overwrite Owner ID if assigned value does not match Account Owner Id
        if (
            property_exists($this->getObj(), 'OwnerId')
            && $this->getObj()->OwnerId
            && $this->_getCustomerOwnerId()
            && $this->_getCustomerOwnerId() != $this->getObj()->OwnerId
        ) {
            $this->getObj()->OwnerId = $this->_getCustomerOwnerId();
        }
        $this->_setCustomerAccountId(NULL);
        $this->_setCustomerOwnerId(NULL);

    }
}