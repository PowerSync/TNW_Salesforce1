<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$mappingTable = $installer->getTable('tnw_salesforce/mapping');
$installer->getConnection()->update($mappingTable, array('sf_magento_enable' => '1', 'sf_magento_type' => 'upsert'), array(
    'local_field = ?' => 'Order Item : unit_price_including_tax_and_discounts',
    'sf_field = ?' => 'UnitPrice',
    'sf_object = ?' => 'OrderItem',
));

$installer->getConnection()->update($mappingTable, array('sf_magento_enable' => '1', 'sf_magento_type' => 'upsert'), array(
    'local_field = ?' => 'Order Item : qty_ordered',
    'sf_field = ?' => 'Quantity',
    'sf_object = ?' => 'OrderItem',
));

$installer->endSetup();