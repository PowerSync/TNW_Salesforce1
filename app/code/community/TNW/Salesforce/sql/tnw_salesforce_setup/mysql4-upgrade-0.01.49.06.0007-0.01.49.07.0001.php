<?php
/**
 * @var $this Mage_Core_Model_Resource_Setup
 */
$installer = $this;

$installer->startSetup();

$setup = new Mage_Sales_Model_Resource_Setup('core_setup');

$setup->addAttribute('quote', 'sf_insync', array(
    'label' => 'In Sync',
    'type' => 'int',
    'input' => 'boolean',
    'visible' => false,
    'system' => true,
    'required' => false,
    'position' => 1,
    'default' => 0,
    'user_defined' => 0,
    'source' => 'eav/entity_attribute_source_boolean'
));

$installer->endSetup();

