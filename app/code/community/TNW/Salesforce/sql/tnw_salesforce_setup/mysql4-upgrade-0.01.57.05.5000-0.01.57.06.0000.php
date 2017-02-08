<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$data = array(
    'local_field' => 'Cart : sf_close_date'
);

$where = array (
    'local_field = ?' => 'Cart : updated_at',
    'sf_field = ?' => 'CloseDate',
    'sf_object = ?' => 'Abandoned',
);

$mappingTable = $installer->getTable('tnw_salesforce/mapping');

$installer->getConnection()->update($mappingTable, $data, $where);

$installer->endSetup();