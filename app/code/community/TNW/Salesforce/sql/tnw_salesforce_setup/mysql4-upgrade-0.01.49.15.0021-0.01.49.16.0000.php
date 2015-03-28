<?php
$installer = $this;

$installer->startSetup();
$installer->getConnection()->addColumn($installer->getTable('sales/order_payment'), 'salesforce_id', 'varchar(50)');
$installer->endSetup();