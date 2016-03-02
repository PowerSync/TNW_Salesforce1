<?php
/**
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;
$installer->startSetup();

$mappingTable = $installer->getTable('tnw_salesforce/mapping');

$data = array(
    // Order
    array(
        'local_field'       => 'Order : number',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'Order',
    ),
    array(
        'local_field'       => 'Order : website',
        'sf_field'          => 'tnw_mage_basic__Magento_Website__c',
        'sf_object'         => 'Order',
    ),
    array(
        'local_field'       => 'Order : cart_all',
        'sf_field'          => 'Description',
        'sf_object'         => 'Order',
    )
);

$data = array_map(function($value){
    return array_merge(array(
        'default_value'     => null,
        'is_system'         => '1',
        'magento_sf_enable' => '1',
        'magento_sf_type'   => 'upsert',
        'sf_magento_enable' => '1',
        'sf_magento_type'   => 'upsert'
    ), $value);
}, $data);

$installer->getConnection()->insertOnDuplicate($mappingTable, $data);

$installer->endSetup();
