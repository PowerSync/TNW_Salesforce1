<?php
/**
 * Update default order status mappings
 */

/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;

$installer->getConnection()->update(
    $installer->getTable('tnw_salesforce/order_status'),
    array('sf_order_status' => 'Draft'),
    'status != \'complete\''
);

$installer->endSetup();
