<?php
$installer = $this;
$installer->startSetup();

$installer->getConnection()->addColumn(
    $installer->getTable('sales/order_status_history'),
    'salesforce_id',
    'varchar(50)'
);

$installer->endSetup();