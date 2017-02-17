<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$data = array(
    // OrderInvoice
    array(
        'local_field'       => 'Invoice : grand_total',
        'sf_field'          => 'tnw_invoice__Total__c',
        'sf_object'         => 'OrderInvoice',
    ),
    array(
        'local_field'       => 'Invoice : grand_total',
        'sf_field'          => 'tnw_invoice__Total__c',
        'sf_object'         => 'OpportunityInvoice',
    ),

    //OrderShipment
    array(
        'local_field'       => 'Shipment : total_qty',
        'sf_field'          => 'tnw_shipment__Total_Quantity__c',
        'sf_object'         => 'OrderShipment',
    ),
    array(
        'local_field'       => 'Shipment : total_qty',
        'sf_field'          => 'tnw_shipment__Total_Quantity__c',
        'sf_object'         => 'OpportunityShipment',
    ),
);

$uoiData = array();
foreach ($data as $value) {
    $uoiData[] = array_merge(array(
        'default_value'     => null,
        'is_system'         => '0',
        'magento_sf_enable' => '1',
        'magento_sf_type'   => 'upsert',
        'sf_magento_enable' => '0',
        'sf_magento_type'   => 'upsert'
    ), $value);
}

$installer->getConnection()
    ->insertOnDuplicate($installer->getTable('tnw_salesforce/mapping'), $uoiData);

$installer->endSetup();