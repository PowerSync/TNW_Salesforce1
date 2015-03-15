<?php

$installer = $this;

$installer->startSetup();

$installer->run("

DELETE FROM {$this->getTable('tnw_salesforce_mapping')} WHERE sf_object='Lead' AND sf_field IN (Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'disableMagentoSync__c','FirstName','LastName','Email');
DELETE FROM {$this->getTable('tnw_salesforce_mapping')} WHERE sf_object='Product2' AND sf_field IN (Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'disableMagentoSync__c','ProductCode','Name');
DELETE FROM {$this->getTable('tnw_salesforce_mapping')} WHERE sf_object='Opportunity' AND sf_field IN (Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'disableMagentoSync__c','AccountId', Mage::helper('tnw_salesforce/config')->getSalesforcePrefix(). 'Magento_ID__c');

    ");

$installer->endSetup(); 