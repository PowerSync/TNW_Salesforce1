<?php

$installer = $this;

$installer->startSetup();
/*
$installer->run("

-- DROP TABLE IF EXISTS {$this->getTable('tnw_salesforce_group')};
CREATE TABLE {$this->getTable('tnw_salesforce_group')} (
  `group_id` int(11) unsigned NOT NULL auto_increment,
  `customer_group_id` smallint(3),
  `sf_account_code` varchar(20) NOT NULL default '',
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    ");
*/
$installer->endSetup(); 