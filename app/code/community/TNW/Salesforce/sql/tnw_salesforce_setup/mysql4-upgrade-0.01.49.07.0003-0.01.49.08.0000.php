<?php
$installer = $this;

$installer->startSetup();

$installer->run("

DROP TABLE IF EXISTS {$this->getTable('tnw_salesforce_queue')};
CREATE TABLE {$this->getTable('tnw_salesforce_queue')} (
  `entity_id` int(11) unsigned NOT NULL auto_increment,
  `record_ids` longtext NOT NULL default '',
  `mage_object_type` varchar(255) NOT NULL COMMENT 'Magento Model used',
  `sf_object_type` varchar(255) NOT NULL COMMENT 'Salesforce Object',
  PRIMARY KEY (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    ");

$installer->endSetup();