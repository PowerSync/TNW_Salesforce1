<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$data = array(
    // Order
    array(
        'local_field'       => 'Customer : salesforce_account_id',
        'sf_field'          => 'AccountId',
        'sf_object'         => 'Order',
        'magento_sf_enable' => '1',
    ),
    array(
        'local_field'       => 'Customer : salesforce_id',
        'sf_field'          => 'tnw_mage_basic__BillingCustomer__c',
        'sf_object'         => 'Order',
        'magento_sf_enable' => '1',
    ),
    array(
        'local_field'       => 'Customer : salesforce_id',
        'sf_field'          => 'BillToContactId',
        'sf_object'         => 'Order',
        'magento_sf_enable' => '1',
    ),
    array(
        'local_field'       => 'Customer : salesforce_id',
        'sf_field'          => 'ShipToContactId',
        'sf_object'         => 'Order',
        'magento_sf_enable' => '1',
    ),
    array(
        'local_field'       => 'Order : account_salesforce_id',
        'sf_field'          => 'AccountId',
        'sf_object'         => 'Order',
        'sf_magento_enable' => '1',
    ),
    array(
        'local_field'       => 'Order : contact_salesforce_id',
        'sf_field'          => 'BillToContactId',
        'sf_object'         => 'Order',
        'sf_magento_enable' => '1',
    ),
);

$uoiData = array();
foreach ($data as $value) {
    $uoiData[] = array_merge(array(
        'attribute_id'      => null,
        'backend_type'      => null,
        'default_value'     => null,
        'is_system'         => '1',
        'magento_sf_enable' => '0',
        'magento_sf_type'   => 'upsert',
        'sf_magento_enable' => '0',
        'sf_magento_type'   => 'upsert'
    ), $value);
}

$installer->getConnection()
    ->insertOnDuplicate($installer->getTable('tnw_salesforce/mapping'), $uoiData);

$installer->endSetup();