<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->addColumn($this->getTable('tnw_salesforce/entity_cache'), 'website_id', 'INT NOT NULL');

$installer->endSetup();