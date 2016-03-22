<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

$installer = $this;

$installer->startSetup();

$installer->run("

DELETE FROM {$this->getTable('tnw_salesforce_mapping')} WHERE sf_object='Lead' AND sf_field IN ('" . Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . 'disableMagentoSync__c' ."','FirstName','LastName','Email');
DELETE FROM {$this->getTable('tnw_salesforce_mapping')} WHERE sf_object='Product2' AND sf_field IN ('" . Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . 'disableMagentoSync__c' ."','ProductCode','Name');
DELETE FROM {$this->getTable('tnw_salesforce_mapping')} WHERE sf_object='Opportunity' AND sf_field IN ('" . Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . 'disableMagentoSync__c' ."','AccountId', '" . Mage::helper('tnw_salesforce/config')->getSalesforcePrefix(). 'Magento_ID__c' . "');

    ");

$installer->endSetup(); 