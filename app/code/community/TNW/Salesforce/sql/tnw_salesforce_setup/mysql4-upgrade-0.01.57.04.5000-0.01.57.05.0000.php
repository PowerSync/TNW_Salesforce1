<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$data = array(
    // Abandoned
    array(
        'local_field'       => 'Cart : owner_salesforce_id',
        'sf_field'          => 'OwnerId',
        'sf_object'         => 'Abandoned',
    )
);

$mappingTable    = $installer->getTable('tnw_salesforce/mapping');

$uoiData = array();
foreach ($data as $value) {

    $uoiData[] = array_merge(array(
        'default_value'     => null,
        'is_system'         => '1',
        'magento_sf_enable' => '1',
        'magento_sf_type'   => 'upsert',
        'sf_magento_enable' => '1',
        'sf_magento_type'   => 'upsert'
    ), $value);
}

$installer->getConnection()->insertOnDuplicate($mappingTable, $uoiData);

$installer->endSetup();