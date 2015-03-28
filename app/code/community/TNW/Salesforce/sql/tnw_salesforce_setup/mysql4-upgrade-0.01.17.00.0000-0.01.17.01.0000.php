<?php

$installer = $this;

$installer->startSetup();

$installer->run("

  DELETE FROM {$this->getTable('tnw_salesforce_mapping')} WHERE sf_object='OpportunityLineItem' AND sf_field IN ('" . Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'disableMagentoSync__c' ."','UnitPrice','TotalPrice','ServiceDate','Quantity','Description')

    ");

$installer->endSetup(); 