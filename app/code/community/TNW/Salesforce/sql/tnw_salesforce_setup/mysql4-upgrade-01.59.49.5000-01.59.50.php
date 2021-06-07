<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$tableName = $this->getTable('tnw_salesforce_queue_storage');
$db = Mage::getSingleton('core/resource')->getConnection('core_write');

$sql = 'ALTER TABLE `' . $tableName . '` CHANGE id id bigint(20) unsigned NOT NULL AUTO_INCREMENT';
$db->query($sql);

$sql = 'ALTER TABLE `' . $tableName . '`  AUTO_INCREMENT=1';
$db->query($sql);

$db->truncateTable($tableName);

$installer->endSetup();