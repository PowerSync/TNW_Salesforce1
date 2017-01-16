<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

$queueStorage = $this->getTable('tnw_salesforce/queue_storage');
if (!$connection->tableColumnExists($queueStorage, 'website_id')) {
    $connection->addColumn($queueStorage, 'website_id', 'INT NOT NULL');
}

$entityCache = $this->getTable('tnw_salesforce/entity_cache');
if (!$connection->tableColumnExists($entityCache, 'website_id')) {
    $connection->addColumn($entityCache, 'website_id', 'INT NOT NULL');
}

$log = $this->getTable('tnw_salesforce/log');
if (!$connection->tableColumnExists($log, 'website_id')) {
    $connection->addColumn($log, 'website_id', 'INT NOT NULL');
}

$installer->updateAttribute('catalog_product', 'salesforce_id', 'is_global', Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE);
$installer->updateAttribute('catalog_product', 'salesforce_disable_sync', 'is_global', Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE);
$installer->updateAttribute('catalog_product', 'salesforce_campaign_id', 'is_global', Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE);

$installer->endSetup();