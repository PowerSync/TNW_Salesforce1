<?php

/** @var TNW_Salesforce_Model_Mysql4_Setup $installer */
$installer = $this;
$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
$setup->updateAttribute('catalog_product', 'salesforce_campaign_id', 'source_model', 'tnw_salesforce/config_source_product_campaign');

$installer->endSetup();