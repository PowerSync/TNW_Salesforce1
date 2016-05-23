<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;

$installer->startSetup();
$installer->getConnection()->addColumn($installer->getTable('sales/creditmemo'), 'salesforce_id', 'varchar(50)');
$installer->getConnection()->addColumn($installer->getTable('sales/creditmemo'), 'sf_insync', 'boolean default FALSE');

$mappingTable = $installer->getTable('tnw_salesforce/order_creditmemo_status');
$table = $installer->getConnection()
    ->newTable($mappingTable)
    ->addColumn('status_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'ID Status')
    ->addColumn('magento_stage', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'nullable'  => false,
        'unsigned'  => true,
        'default'   => '1',
    ), 'Magento Stagename')
    ->addColumn('salesforce_status', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(
        'nullable'  => false,
        'default'   => '',
    ), 'Salesforce Status')
    ->addIndex($installer->getIdxName('tnw_salesforce/order_creditmemo_status', array('magento_stage', 'salesforce_status')),
        array('magento_stage', 'salesforce_status'))
    ->setComment('Order Credit Memo status mapping');

$installer->getConnection()->createTable($table);
$installer->getConnection()
    ->insertArray($mappingTable, array('magento_stage', 'salesforce_status'), array(
        array('1', 'Draft'),
        array('2', 'Activated'),
    ));

$installer->endSetup();