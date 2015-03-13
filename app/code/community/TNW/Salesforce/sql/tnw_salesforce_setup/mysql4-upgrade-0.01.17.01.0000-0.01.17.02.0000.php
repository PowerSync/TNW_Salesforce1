<?php

$installer = $this;

$installer->startSetup();

$installer->run("

DELETE FROM {$this->getTable('tnw_salesforce_mapping')} WHERE sf_object='Lead' AND sf_field IN (TNW_Salesforce_Helper_Salesforce::CONNECTOR_ENTERPRISE_PERFIX . 'DisableMagentoSync__c','FirstName','LastName','Email');
DELETE FROM {$this->getTable('tnw_salesforce_mapping')} WHERE sf_object='Product2' AND sf_field IN (TNW_Salesforce_Helper_Salesforce::CONNECTOR_ENTERPRISE_PERFIX . 'DisableMagentoSync__c','ProductCode','Name');
DELETE FROM {$this->getTable('tnw_salesforce_mapping')} WHERE sf_object='Opportunity' AND sf_field IN (TNW_Salesforce_Helper_Salesforce::CONNECTOR_ENTERPRISE_PERFIX . 'DisableMagentoSync__c','AccountId', TNW_Salesforce_Helper_Salesforce::CONNECTOR_ENTERPRISE_PERFIX. 'Magento_ID__c');

    ");

$installer->endSetup(); 