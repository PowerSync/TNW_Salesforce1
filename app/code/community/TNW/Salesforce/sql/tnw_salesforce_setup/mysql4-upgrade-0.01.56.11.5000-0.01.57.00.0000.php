<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$connection = $this->getConnection();

$tableImport = $installer->getTable('tnw_salesforce/import');
$connection->delete($tableImport, 'is_processing IS NOT NULL');
$connection->addColumn($tableImport, 'status', 'VARCHAR(50) NULL DEFAULT \''.TNW_Salesforce_Model_Import::STATUS_NEW.'\'');
$connection->addColumn($tableImport, 'message', 'TEXT NULL DEFAULT NULL');
$connection->dropColumn($tableImport, 'is_processing');

$installer->endSetup();