<?php

/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;

$installer->startSetup();

$installer->getConnection()->addColumn(
    $installer->getTable('tnw_salesforce/mapping'),
    'active',
    array(
        'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
        'length' => 2,
        'default' => 1,
        'nullable' => false,
        'comment' => 'Is this record active'

    )
);

$installer->endSetup(); 