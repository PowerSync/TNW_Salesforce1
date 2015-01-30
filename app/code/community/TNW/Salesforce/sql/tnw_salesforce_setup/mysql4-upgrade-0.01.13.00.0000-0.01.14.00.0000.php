<?php

$installer = $this;

$installer->startSetup();

$installer->run("

DROP TABLE IF EXISTS {$this->getTable('tnw_salesforce_order_status')};
CREATE TABLE {$this->getTable('tnw_salesforce_order_status')} (
  `status_id` int(11) unsigned NOT NULL auto_increment,
  `status` varchar(32),
  `sf_lead_status_code` varchar(20) NOT NULL default '',
  `sf_opportunity_status_code` varchar(20) NOT NULL default '',
  PRIMARY KEY (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    ");

$installer->endSetup(); 