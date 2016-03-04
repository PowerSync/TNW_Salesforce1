<?php

$installer = $this;

$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
/* Adding Salesforce ID to Quote */
$setup->addAttribute('catalog_product', 'salesforce_campaign_id', array(
    'label' => 'Salesforce Campaign',
    'type' => 'varchar',
    'input' => 'select',
    'source' => 'tnw_salesforce/api_entity_resource_campaign_collection',
    'visible' => true,
    'required' => false,
    'unique' => 1,
    'position' => 50,
    'group' => 'Salesforce'
));

$installer->endSetup(); 