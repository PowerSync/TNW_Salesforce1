<?php

$installer = $this;

$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

/* Adding Salesforce ID to Customer object */
$setup->addAttribute('customer', 'salesforce_id', array(
    'label' => 'Salesforce ID',
    'type' => 'varchar',
    'input' => 'text',
    'visible' => false,
    'required' => false,
    'position' => 1,
));

/* Adding Salesforce ID to Quote */
$setup->addAttribute('catalog_product', 'salesforce_id', array(
    'label' => 'Salesforce ID',
    'type' => 'varchar',
    'input' => 'text',
    'visible' => true,
    'required' => false,
    'unique' => 1,
    'position' => 1,
    'group' => 'Salesforce'
));

$installer->endSetup(); 