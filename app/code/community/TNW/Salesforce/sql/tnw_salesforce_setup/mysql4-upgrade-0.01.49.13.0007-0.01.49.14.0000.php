<?php
$installer = $this;

$installer->startSetup();
$installer->getConnection()->addColumn($installer->getTable('sales/order'), 'contact_salesforce_id', 'varchar(50)');
$installer->getConnection()->addColumn($installer->getTable('sales/order'), 'account_salesforce_id', 'varchar(50)');
$installer->getConnection()->addColumn($installer->getTable('sales/quote'), 'contact_salesforce_id', 'varchar(50)');
$installer->getConnection()->addColumn($installer->getTable('sales/quote'), 'account_salesforce_id', 'varchar(50)');

$installer->endSetup();