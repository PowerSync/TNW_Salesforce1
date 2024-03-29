<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$table = $installer->getConnection()
    ->newTable($installer->getTable('tnw_salesforce/entity_cache'))
    ->addColumn('id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 225, array(
        'nullable' => false,
        'default' => '',
    ), 'Salesforce Id')
    ->addColumn('name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 225, array(
        'nullable' => false,
        'default' => '',
    ), 'Salesforce Name')
    ->addColumn('object_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 225, array(
        'nullable' => false,
        'default' => '',
    ), 'Salesforce Object Type')
    ->addIndex($installer->getIdxName('tnw_salesforce/entity_cache', 'id', Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE), 'id', array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE))
    ->addIndex($installer->getIdxName('tnw_salesforce/entity_cache', 'name'), 'name')
    ->setComment('Entity cache');

$installer->getConnection()->createTable($table);

$installer->endSetup();