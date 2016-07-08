<?php
/**
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;
$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
$setup->updateAttribute('catalog_product', 'salesforce_campaign_id', 'source_model');
$setup->updateAttribute('catalog_product', 'salesforce_campaign_id', 'frontend_input_renderer');
$setup->updateAttribute('catalog_product', 'salesforce_campaign_id', 'frontend_input', 'text');

$installer->endSetup();