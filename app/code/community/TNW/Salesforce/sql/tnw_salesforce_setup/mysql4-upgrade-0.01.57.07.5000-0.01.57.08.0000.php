<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$mappingTable = $installer->getTable('tnw_salesforce/mapping');
$installer->getConnection()->update($mappingTable, array('magento_sf_type' => 'insert'), array(
    'local_field = ?' => 'Customer : sf_record_type',
    'sf_field = ?' => 'RecordTypeId',
    'sf_object = ?' => 'Account',
));

$installer->endSetup();