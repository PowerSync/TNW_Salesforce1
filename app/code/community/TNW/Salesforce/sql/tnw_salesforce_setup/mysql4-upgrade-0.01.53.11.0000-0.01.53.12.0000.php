<?php

/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;

$installer->startSetup();

$installer->getConnection()->addColumn(
    $installer->getTable('tnw_salesforce/log'),
    'transaction_id',
    'varchar(50)'
);

$installer->endSetup(); 