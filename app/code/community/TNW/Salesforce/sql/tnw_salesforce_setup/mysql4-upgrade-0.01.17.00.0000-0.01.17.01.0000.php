<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

$installer = $this;

$installer->startSetup();

$installer->run("

  DELETE FROM {$this->getTable('tnw_salesforce_mapping')} WHERE sf_object='OpportunityLineItem' AND sf_field IN ('" . Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . 'disableMagentoSync__c' ."','UnitPrice','TotalPrice','ServiceDate','Quantity','Description')

    ");

$installer->endSetup(); 