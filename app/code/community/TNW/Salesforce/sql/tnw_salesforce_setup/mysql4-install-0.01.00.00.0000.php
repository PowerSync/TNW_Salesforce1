<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

$installer = $this;

$installer->startSetup();

$installer->run("

DROP TABLE IF EXISTS {$this->getTable('tnw_salesforce_mapping')};
CREATE TABLE {$this->getTable('tnw_salesforce_mapping')} (
  `mapping_id` int(11) unsigned NOT NULL auto_increment,
  `local_field` varchar(255) NOT NULL default '',
  `sf_field` varchar(255) NOT NULL default '',
  `sf_object` varchar(255) NOT NULL default '',
  `default_value` varchar(255) NULL,
  PRIMARY KEY (`mapping_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    ");

$installer->endSetup(); 