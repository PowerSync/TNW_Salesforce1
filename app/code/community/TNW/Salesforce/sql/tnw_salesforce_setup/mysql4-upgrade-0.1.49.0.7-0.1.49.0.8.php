<?php

$installer = $this;

$installer->startSetup();

$installer->getConnection()->addColumn($installer->getTable('tnw_salesforce_order_status'), 'sf_order_status', 'varchar(100)');

$installer->endSetup();

