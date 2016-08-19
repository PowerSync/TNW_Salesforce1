<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$mappingTable = $installer->getTable('tnw_salesforce/mapping');

$data = array(
    array(
        'local_field'       => 'Custom : RecordTypeId',
        'sf_field'          => 'RecordTypeId',
        'sf_object'         => 'Order',
        'default_value'     => 'salesforce_order/customer_opportunity/order_default_record_type',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Custom : RecordTypeId',
        'sf_field'          => 'RecordTypeId',
        'sf_object'         => 'Opportunity',
        'default_value'     => 'salesforce_order/customer_opportunity/opportunity_default_record_type',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Custom : RecordTypeId',
        'sf_field'          => 'RecordTypeId',
        'sf_object'         => 'OrderCreditMemo',
        'default_value'     => 'salesforce_creditmemo/creditmemo_configuration/default_record_type',
        'sf_magento_enable' => '0',
    ),
);

$uoiData = array();
foreach ($data as $value) {
    $uoiData[] = array_merge(array(
        'attribute_id'      => null,
        'backend_type'      => null,
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