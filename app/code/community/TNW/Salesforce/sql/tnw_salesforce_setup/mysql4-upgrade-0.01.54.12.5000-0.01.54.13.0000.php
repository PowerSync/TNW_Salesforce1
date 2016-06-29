<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$uoiData = array(array(
    'local_field'       => 'Order : created_at',
    'sf_field'          => 'EffectiveDate',
    'sf_object'         => 'Order',
    'attribute_id'      => null,
    'backend_type'      => 'datetime',
    'default_value'     => null,
    'is_system'         => '1',
    'magento_sf_enable' => '1',
    'magento_sf_type'   => 'upsert',
    'sf_magento_enable' => '1',
    'sf_magento_type'   => 'insert'
));

$mappingTable = $installer->getTable('tnw_salesforce/mapping');
$installer->getConnection()->insertOnDuplicate($mappingTable, $uoiData);
$installer->endSetup();