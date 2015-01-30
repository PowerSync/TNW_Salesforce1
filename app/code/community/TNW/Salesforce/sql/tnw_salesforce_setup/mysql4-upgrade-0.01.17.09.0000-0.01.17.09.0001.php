<?php
$installer = $this;

$installer->startSetup();
/*
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
$attributes = array(
    'position' => 1,
    'type' => 'text',
    'label' => 'Salesforce ID',
    'global' => 1,
    'visible' => 1,
    'required' => 0,
    'user_defined' => 1,
    'searchable' => 0,
    'filterable' => 0,
    'comparable' => 0,
    'visible_on_front' => 1,
    'visible_in_advanced_search' => 0,
    'unique' => 0,
    'is_configurable' => 0,
    'position' => 1,
);
$setup->addAttribute('order', 'salesforce_id', $attribute);
*/
$installer->getConnection()->addColumn($installer->getTable('sales/order'), 'salesforce_id', 'varchar(50)');
$installer->getConnection()->addColumn($installer->getTable('sales/quote'), 'salesforce_id', 'varchar(50)');
$installer->endSetup();