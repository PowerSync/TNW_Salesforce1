<?php

$installer = $this;
$installer->startSetup();
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

// Remove was throwing an error because the attribute did not exist
$setup->addAttribute('catalog_product', 'salesforce_disable_sync', array(
    'label' => 'Disable Synchronization',
    'type' => 'int',
    'input' => 'boolean',
    'visible' => false,
    'is_visible' => false,
    'system' => true,
    'required' => false,
    'unique' => 0,
    'position' => 1,
    'default' => 0,
    'user_defined' => 1,
    'group' => 'Salesforce',
));

$installer->endSetup();