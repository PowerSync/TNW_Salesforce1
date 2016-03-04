<?php
/**
 * @var $this Mage_Core_Model_Resource_Setup
 */

$installer = $this;

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$setup->updateAttribute('catalog_product', 'salesforce_pricebook_id', 'frontend_input', 'textarea');
$setup->updateAttribute('catalog_product', 'salesforce_pricebook_id', 'backend_type', 'text');

$installer->endSetup();
