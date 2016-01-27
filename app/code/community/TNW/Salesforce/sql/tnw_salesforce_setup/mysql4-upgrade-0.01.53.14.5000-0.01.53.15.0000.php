<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;

$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
$installer->getConnection()->modifyColumn(
    $installer->getTable('tnw_salesforce/log'),
    'entity_id',
    'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT \'ID Field\','
);

$installer->endSetup();