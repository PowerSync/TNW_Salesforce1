<?php

$installer = $this;

$installer->startSetup();

$installer->run("

DELETE FROM {$this->getTable('tnw_salesforce_mapping')} WHERE sf_object='OpportunityLineItem' AND sf_field IN (TNW_Salesforce_Helper_Salesforce::CONNECTOR_ENTERPRISE_PERFIX . 'DisableMagentoSync__c','UnitPrice','TotalPrice','ServiceDate','Quantity','Description')

    ");

$installer->endSetup(); 