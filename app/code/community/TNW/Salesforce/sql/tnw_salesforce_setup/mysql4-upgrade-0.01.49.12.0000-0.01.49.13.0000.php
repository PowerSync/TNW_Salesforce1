<?php
$installer = $this;

$installer->startSetup();
$installer->getConnection()->addColumn($this->getTable('sales_flat_invoice_item'), 'salesforce_id', 'varchar(50)');
$installer->endSetup();