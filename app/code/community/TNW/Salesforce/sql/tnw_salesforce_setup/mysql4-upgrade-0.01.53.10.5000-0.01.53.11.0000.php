<?php

/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;

$installer->startSetup();

if (!$installer->tableExists($installer->getTable('tnw_salesforce/log'))) {

    $table = $installer->getConnection()
        ->newTable($installer->getTable('tnw_salesforce/log'))
        ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity' => true,
            'nullable' => false,
            'primary' => true,
        ), 'ID Field')
        ->addColumn('level', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => false,
            'default' => '0',
        ), 'Record level')
        ->addColumn('message', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable' => false,
            'default' => '',
        ), 'Log message')
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
            'nullable' => false,
            'default' => 'NOW()',
        ), 'Log date')
        ->setComment('Synchronization log table');


    $installer->getConnection()->createTable($table);
}

$installer->endSetup(); 