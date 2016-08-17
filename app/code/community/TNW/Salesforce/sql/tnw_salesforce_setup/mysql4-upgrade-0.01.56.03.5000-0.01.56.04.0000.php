<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$configTable  = $installer->getTable('core/config_data');
$mappingTable = $installer->getTable('tnw_salesforce/mapping');
$connection   = $installer->getConnection();

$replace = array(
    //abandoned
    'salesforce_order/customer_opportunity/abandoned_cart_enabled'              => 'salesforce_order/abandoned_carts/abandoned_cart_enabled',
    'salesforce_order/customer_opportunity/abandoned_cart_limit'                => 'salesforce_order/abandoned_carts/abandoned_cart_limit',
    'salesforce_order/customer_opportunity/abandoned_close_time_after'          => 'salesforce_order/abandoned_carts/abandoned_close_time_after',
    'salesforce_order/customer_opportunity/abandoned_cart_state'                => 'salesforce_order/abandoned_carts/abandoned_cart_state',

    //promotion
    'salesforce_order/salesforce_campaigns/sync_enabled'                        => 'salesforce_promotion/salesforce_campaigns/sync_enabled',
    'salesforce_order/salesforce_campaigns/create_campaign_automatic'           => 'salesforce_promotion/salesforce_campaigns/create_campaign_automatic',

    //invoice
    'salesforce_order/invoice_configuration/invoice_sync_enable'                => 'salesforce_invoice/invoice_configuration/sync_enable',

    //shipment
    'salesforce_order/shipment_configuration/sync_enabled'                      => 'salesforce_shipment/shipment_configuration/sync_enabled',

    //creditmemo
    'salesforce_order/creditmemo_configuration/sync_enabled'                    => 'salesforce_creditmemo/creditmemo_configuration/sync_enabled',
);

foreach ($replace as $where=>$bind) {
    $connection->update($configTable, array('path' => $bind), "path LIKE '$where'");
}

$replaceMapping = array(
    'salesforce_order/salesforce_campaigns/default_status' => 'salesforce_promotion/salesforce_campaigns/default_status',
    'salesforce_order/salesforce_campaigns/default_type'   => 'salesforce_promotion/salesforce_campaigns/default_type',
    'salesforce_order/salesforce_campaigns/default_owner'  => 'salesforce_promotion/salesforce_campaigns/default_owner',
);

foreach ($replaceMapping as $where=>$bind) {
    $connection->update($mappingTable, array('default_value' => $bind), "default_value LIKE '$where'");
}

$select = $connection->select()
    ->from($configTable, array('value'))
    ->where('path LIKE \'salesforce_order/general/notes_synchronize\'');

$value = $connection->fetchOne($select);

$connection->insertMultiple($configTable, array(
    array('scope'=>'default', 'scope_id' => 0, 'path'=>'salesforce_invoice/invoice_configuration/notes_synchronize', 'value'=>$value),
    array('scope'=>'default', 'scope_id' => 0, 'path'=>'salesforce_shipment/shipment_configuration/notes_synchronize', 'value'=>$value),
    array('scope'=>'default', 'scope_id' => 0, 'path'=>'salesforce_creditmemo/creditmemo_configuration/notes_synchronize', 'value'=>$value),
));

$installer->endSetup();