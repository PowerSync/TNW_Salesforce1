<?php
$installer = $this;

$installer->startSetup();
$installer->getConnection()->addColumn($this->getTable('sales_flat_order_item'), 'salesforce_id', 'varchar(50)');
$installer->getConnection()->addColumn($this->getTable('sales_flat_quote_item'), 'salesforce_id', 'varchar(50)');
$installer->getConnection()->addColumn($this->getTable('sales_flat_shipment_item'), 'salesforce_id', 'varchar(50)');
$installer->endSetup();