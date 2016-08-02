<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$configTable = $installer->getTable('core/config_data');
$installer->getConnection()->update($configTable, array('path' => 'salesforce_customer/customer_view/opportunity_display'),
    'path LIKE \'salesforce_order/customer_view/opportunity_display\'');
$installer->getConnection()->update($configTable, array('path' => 'salesforce_customer/customer_view/opportunity_filter'),
    'path LIKE \'salesforce_order/customer_view/opportunity_filter\'');

$installer->endSetup();