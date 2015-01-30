<?php
$installer = $this;

$installer->startSetup();
$installer->getConnection()->addColumn($installer->getTable('sales/shipment_item'), 'salesforce_id', 'varchar(50)');
$installer->endSetup();