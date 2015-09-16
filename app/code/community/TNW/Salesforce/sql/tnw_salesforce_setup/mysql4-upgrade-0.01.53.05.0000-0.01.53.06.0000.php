<?php

$installer = $this;

$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

/* Adding Salesforce ID to Quote */
$setup->updateAttribute('catalog_product', 'salesforce_campaign_id', 'is_unique', 0);

$installer->endSetup(); 