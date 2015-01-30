<?php

$installer = $this;

$installer->startSetup();

$installer->run("

DELETE FROM {$this->getTable('tnw_salesforce_mapping')} WHERE sf_object='OpportunityLineItem' AND sf_field IN ('tnw_powersync__DisableMagentoSync__c','UnitPrice','TotalPrice','ServiceDate','Quantity','Description')

    ");

$installer->endSetup(); 