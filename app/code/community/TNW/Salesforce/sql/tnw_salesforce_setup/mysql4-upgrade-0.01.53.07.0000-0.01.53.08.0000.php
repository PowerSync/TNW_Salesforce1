<?php

$installer = $this;

$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

/* Adding Salesforce ID to Quote */
$setup->updateAttribute('catalog_product', 'salesforce_campaign_id', 'frontend_input_renderer', 'tnw_salesforce/adminhtml_catalog_product_helper_chosen');

$installer->endSetup(); 