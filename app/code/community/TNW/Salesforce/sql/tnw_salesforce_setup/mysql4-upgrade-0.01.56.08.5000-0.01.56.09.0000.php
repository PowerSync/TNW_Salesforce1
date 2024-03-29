<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$setup = new Mage_Customer_Model_Resource_Setup('core_setup');

$setup->updateAttribute('customer', 'salesforce_contact_owner_id', 'frontend_label', 'Sales Person');
$setup->updateAttribute('customer', 'salesforce_contact_owner_id', 'is_visible', '1');

$setup->updateAttribute('customer', 'salesforce_account_owner_id', 'frontend_label', 'Salesforce Account Owner');
$setup->updateAttribute('customer', 'salesforce_account_owner_id', 'is_visible', '1');

$setup->updateAttribute('customer', 'salesforce_lead_owner_id', 'frontend_label', 'Salesforce Lead Owner');
$setup->updateAttribute('customer', 'salesforce_lead_owner_id', 'is_visible', '1');

Mage::getModel('customer/attribute')->loadByCode('customer', 'salesforce_contact_owner_id')
    ->setData('used_in_forms', array('adminhtml_customer'))
    ->setData('sort_order', 100)
    ->save();

Mage::getModel('customer/attribute')->loadByCode('customer', 'salesforce_account_owner_id')
    ->setData('used_in_forms', array('adminhtml_customer'))
    ->setData('sort_order', 101)
    ->save();

Mage::getModel('customer/attribute')->loadByCode('customer', 'salesforce_lead_owner_id')
    ->setData('used_in_forms', array('adminhtml_customer'))
    ->setData('sort_order', 102)
    ->save();

$installer->endSetup();