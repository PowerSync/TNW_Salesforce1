<?php

$installer = $this;

$installer->startSetup();

$installer->run("

DELETE FROM {$this->getTable('tnw_salesforce_mapping')} WHERE sf_object='Lead' AND sf_field IN ('tnw_powersync__DisableMagentoSync__c','FirstName','LastName','Email');
DELETE FROM {$this->getTable('tnw_salesforce_mapping')} WHERE sf_object='Product2' AND sf_field IN ('tnw_powersync__DisableMagentoSync__c','ProductCode','Name');
DELETE FROM {$this->getTable('tnw_salesforce_mapping')} WHERE sf_object='Opportunity' AND sf_field IN ('tnw_powersync__DisableMagentoSync__c','AccountId','tnw_powersync__Magento_ID__c');

    ");

$installer->endSetup(); 