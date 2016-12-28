<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->addColumn($this->getTable('tnw_salesforce/queue_storage'), 'website_id', 'INT NOT NULL');

$installer->endSetup();