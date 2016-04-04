<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$mappingTable = $installer->getTable('catalogrule/rule');
$installer->getConnection()
    ->addColumn($mappingTable, 'sf_insync', array(
        'type'      => Varien_Db_Ddl_Table::TYPE_SMALLINT,
        'length'    => 1,
        'default'   => 0,
        'nullable'  => false,
        'comment'   => 'Is sync'
    ));

$installer->getConnection()
    ->addColumn($mappingTable, 'salesforce_id', 'varchar(50)');

$mappingTable = $installer->getTable('salesrule/rule');
$installer->getConnection()
    ->addColumn($mappingTable, 'sf_insync', array(
        'type'      => Varien_Db_Ddl_Table::TYPE_SMALLINT,
        'length'    => 1,
        'default'   => 0,
        'nullable'  => false,
        'comment'   => 'Is sync'
    ));

$installer->getConnection()
    ->addColumn($mappingTable, 'salesforce_id', 'varchar(50)');

$installer->endSetup();