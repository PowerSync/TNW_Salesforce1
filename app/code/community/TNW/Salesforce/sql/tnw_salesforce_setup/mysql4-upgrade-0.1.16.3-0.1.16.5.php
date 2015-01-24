<?php

$installer = $this;

$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
/* Adding Salesforce ID to Quote */
$setup->addAttribute('catalog_product', 'salesforce_pricebook_id', array(
    'label' => 'Salesforce Pricebook ID',
    'type' => 'varchar',
    'input' => 'text',
    'visible' => true,
    'required' => false,
    'unique' => 1,
    'position' => 1,
    'group' => 'Salesforce'
));

$installer->endSetup(); 