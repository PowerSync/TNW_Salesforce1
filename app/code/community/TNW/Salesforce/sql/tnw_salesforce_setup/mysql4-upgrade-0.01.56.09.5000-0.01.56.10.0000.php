<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$this->getConnection()
    ->addColumn($installer->getTable('tnw_salesforce/queue_storage'), 'sync_type', 'varchar(50)');

$installer->endSetup();