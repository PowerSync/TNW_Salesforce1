<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$setup->addAttribute('customer', 'salesforce_lead_owner_id', array(
    'label' => 'Salesforce Lead Owner ID',
    'type' => 'varchar',
    'input' => 'text',
    'visible' => false,
    'required' => false,
    'position' => 1,
));

$setup->addAttribute('customer', 'salesforce_contact_owner_id', array(
    'label' => 'Salesforce Contact Owner ID',
    'type' => 'varchar',
    'input' => 'text',
    'visible' => false,
    'required' => false,
    'position' => 1,
));

$setup->addAttribute('customer', 'salesforce_account_owner_id', array(
    'label' => 'Salesforce Contact Owner ID',
    'type' => 'varchar',
    'input' => 'text',
    'visible' => false,
    'required' => false,
    'position' => 1,
));

$installer->endSetup();