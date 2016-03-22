<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$mappingTable = $installer->getTable('tnw_salesforce/mapping');
$installer->getConnection()->addColumn($mappingTable, 'is_system', array(
    'type'      => Varien_Db_Ddl_Table::TYPE_SMALLINT,
    'length'    => 2,
    'default'   => 0,
    'nullable'  => false,
    'comment'   => 'Is system'
));

$installer->getConnection()->addIndex(
    $mappingTable,
    $installer->getIdxName(
        'tnw_salesforce/mapping',
        array('local_field', 'sf_object', 'sf_field'),
        Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
    ),
    array('local_field', 'sf_object', 'sf_field'),
    Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
);

$installer->endSetup();