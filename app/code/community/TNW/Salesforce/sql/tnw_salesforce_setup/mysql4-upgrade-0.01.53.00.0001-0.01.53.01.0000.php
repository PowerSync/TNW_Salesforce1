<?php

/**
 * @var Mage_Sales_Model_Resource_Setup $this
 */

$installer = $this;

$installer->startSetup();

$installer->getConnection()->addColumn(
    $installer->getTable('sales/shipment'),
    'salesforce_id',
    'varchar(255)'
);

$installer->getConnection()->addColumn(
    $installer->getTable('sales/shipment_item'),
    'salesforce_id',
    'varchar(255)'
);

$installer->endSetup(); 